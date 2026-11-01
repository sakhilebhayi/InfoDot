<?php

namespace Tests\Feature;

use App\Mail\EnquiryActivity;
use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Enquiries\EnquiryService;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * R-33 Phase 3: the InfoDot Inbox. A person messages a business; the business's owner and
 * administrators (or the member it is given to) answer as the business; internal notes and who
 * answered never reach the customer; limits and blocks stop abuse; conversations stay isolated.
 */
class EnquiryInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $editor;

    private User $customer;

    private Team $acme;

    private const BODY = 'We need a borehole drilled on a farm near Hazyview, about 150 metres. When could you come?';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->owner = User::factory()->withPersonalTeam()->create(['name' => 'Olive Owner']);
        $this->admin = User::factory()->withPersonalTeam()->create(['name' => 'Adam Admin']);
        $this->editor = User::factory()->withPersonalTeam()->create(['name' => 'Eddie Editor']);
        $this->customer = User::factory()->withPersonalTeam()->create(['name' => 'Cara Customer']);

        $this->acme = Team::forceCreate(['user_id' => $this->owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $this->acme->profileOrNew()->forceFill(['visibility' => 'public', 'description' => 'We drill boreholes.'])->save();
        $this->acme->users()->attach($this->admin, ['role' => 'admin']);
        $this->acme->users()->attach($this->editor, ['role' => 'editor']);
    }

    private function send(?User $as = null, array $overrides = []): TestResponse
    {
        return $this->actingAs($as ?? $this->customer)->post('/business/'.$this->acme->uuid.'/message', $overrides + [
            'subject' => 'Borehole quote', 'category' => 'quote', 'body' => self::BODY,
        ]);
    }

    private function enquiry(): Enquiry
    {
        $this->send()->assertRedirect();

        return Enquiry::latest('id')->firstOrFail();
    }

    private function inbox(string $path = ''): string
    {
        return '/business/'.$this->acme->uuid.'/inbox'.$path;
    }

    public function test_a_person_messages_the_business_and_its_owner_and_administrators_are_told(): void
    {
        $e = $this->enquiry();

        $this->assertSame(Enquiry::NEW, $e->status);
        $this->assertSame($this->acme->id, $e->team_id);
        $this->get('/inbox/'.$e->uuid)->assertOk()->assertSee('Borehole quote')->assertSee(self::BODY)->assertSee('Sent');

        foreach ([$this->owner, $this->admin] as $handler) {
            $this->assertDatabaseHas('user_notices', ['user_id' => $handler->id, 'kind' => UserNotice::ENQUIRY_RECEIVED, 'subject_id' => $e->id]);
            Mail::assertSent(EnquiryActivity::class, fn (EnquiryActivity $m) => $m->hasTo($handler->email));
        }
        // An editor who was not given it is not told, and the email never carries the message.
        $this->assertDatabaseMissing('user_notices', ['user_id' => $this->editor->id]);
        Mail::assertSent(EnquiryActivity::class, fn (EnquiryActivity $m) => ! str_contains($m->render(), 'Hazyview'));

        $notice = UserNotice::where('user_id', $this->owner->id)->sole();
        $words = app(InAppNotices::class)->describe($notice);
        $this->assertStringContainsString('New enquiry for Acme Boreholes from Cara Customer', $words['text']);
        $this->assertSame(route('business.inbox.show', [$this->acme, $e]), $words['url']);
    }

    public function test_the_business_answers_as_the_business_and_notes_never_reach_the_customer(): void
    {
        $e = $this->enquiry();

        $this->actingAs($this->admin)->get($this->inbox())->assertOk()->assertSee('Borehole quote');
        $this->actingAs($this->admin)->post($this->inbox('/'.$e->uuid.'/reply'), ['body' => 'Internal: check rig availability first.', 'internal' => 1])->assertRedirect();
        $this->actingAs($this->admin)->post($this->inbox('/'.$e->uuid.'/reply'), ['body' => 'We can be there on Tuesday.'])->assertRedirect();

        $e->refresh();
        $this->assertSame(Enquiry::AWAITING, $e->status);
        $this->assertNotNull($e->first_response_at);
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->customer->id, 'kind' => UserNotice::ENQUIRY_REPLY]);

        $this->actingAs($this->customer)->get('/inbox/'.$e->uuid)->assertOk()
            ->assertSee('We can be there on Tuesday.')
            ->assertSee('Acme Boreholes')
            ->assertSee('Waiting for your reply')
            ->assertDontSee('Internal: check rig availability first.')
            ->assertDontSee('Adam Admin');

        // The customer answers; the conversation is open again and the business is told.
        $this->actingAs($this->customer)->post('/inbox/'.$e->uuid.'/reply', ['body' => 'Tuesday works, thank you.'])->assertRedirect();
        $this->assertSame(Enquiry::OPEN, $e->fresh()->status);
        $this->assertSame(2, UserNotice::where('user_id', $this->owner->id)->count());
    }

    public function test_assigned_members_see_only_what_they_were_given(): void
    {
        $e = $this->enquiry();
        $other = $this->enquiry2 = null;
        $this->actingAs($this->editor)->get($this->inbox('/'.$e->uuid))->assertNotFound();
        $this->actingAs($this->editor)->get($this->inbox())->assertOk()->assertDontSee('Borehole quote');

        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid), ['status' => 'in_progress', 'assigned_to' => $this->editor->id])->assertRedirect();
        $this->assertSame($this->editor->id, $e->fresh()->assigned_to);
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->editor->id, 'kind' => UserNotice::ENQUIRY_ASSIGNED]);

        $this->actingAs($this->editor)->get($this->inbox())->assertSee('Borehole quote');
        $this->actingAs($this->editor)->get($this->inbox('/'.$e->uuid))->assertOk()->assertSee('Assigned to Eddie Editor');
        $this->actingAs($this->editor)->post($this->inbox('/'.$e->uuid.'/reply'), ['body' => 'I will handle this one.'])->assertRedirect();

        // A member cannot pass it on or take over assignment.
        $this->actingAs($this->editor)->post($this->inbox('/'.$e->uuid), ['status' => 'in_progress', 'assigned_to' => $this->admin->id])->assertRedirect();
        $this->assertSame($this->editor->id, $e->fresh()->assigned_to);

        // Once assigned, the customer's next message goes to the assignee only.
        UserNotice::query()->delete();
        $this->actingAs($this->customer)->post('/inbox/'.$e->uuid.'/reply', ['body' => 'Thanks, Tuesday is good for us.'])->assertRedirect();
        $this->assertSame([$this->editor->id], UserNotice::pluck('user_id')->all());
    }

    public function test_conversations_stay_isolated_between_people_and_businesses(): void
    {
        $e = $this->enquiry();
        $stranger = User::factory()->withPersonalTeam()->create();
        $otherOwner = User::factory()->withPersonalTeam()->create();
        $other = Team::forceCreate(['user_id' => $otherOwner->id, 'name' => 'Other Co', 'personal_team' => false]);

        $this->actingAs($stranger)->get('/inbox/'.$e->uuid)->assertNotFound();
        $this->actingAs($stranger)->post('/inbox/'.$e->uuid.'/reply', ['body' => 'Butting in here.'])->assertNotFound();
        $this->actingAs($stranger)->get($this->inbox())->assertForbidden();
        $this->actingAs($otherOwner)->get('/business/'.$other->uuid.'/inbox/'.$e->uuid)->assertNotFound();
        $this->actingAs($otherOwner)->get($this->inbox('/'.$e->uuid))->assertNotFound();
        $this->actingAs($otherOwner)->post($this->inbox('/'.$e->uuid), ['status' => 'closed'])->assertNotFound();
        $this->assertSame(Enquiry::NEW, $e->fresh()->status);
    }

    public function test_limits_blocks_and_switches_stop_abuse(): void
    {
        $this->send()->assertSessionHasNoErrors();
        $this->send()->assertSessionHasErrors('body'); // the same words twice
        $this->send(null, ['body' => self::BODY.' Also, do you install pumps?'])->assertSessionHasNoErrors();
        $this->send(null, ['body' => 'A third different message today to the same business.'])->assertSessionHasErrors('body');

        // Members of the business cannot message it; a short message is refused.
        $this->send($this->editor)->assertSessionHasErrors('body');
        $fresh = User::factory()->withPersonalTeam()->create();
        $this->send($fresh, ['body' => 'Hi'])->assertSessionHasErrors('body');

        // The owner blocks the sender: no new enquiries from them, the old conversation stays.
        $e = Enquiry::firstOrFail();
        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid.'/block'))->assertRedirect();
        $this->travel(2)->days();
        $this->send()->assertSessionHasErrors('body');
        $this->actingAs($this->customer)->get('/inbox/'.$e->uuid)->assertOk();
        $this->actingAs($this->editor)->post($this->inbox('/'.$e->uuid.'/block'))->assertNotFound();

        // A business that switches enquiries off shows no button and takes no messages.
        $this->acme->profile->forceFill(['accepts_enquiries' => false])->save();
        $this->actingAs($fresh)->get('/business/'.$this->acme->uuid)->assertDontSee('Message business');
        $this->send($fresh)->assertSessionHasErrors('body');
    }

    public function test_hidden_businesses_and_guests(): void
    {
        $this->get('/business/'.$this->acme->uuid.'/message')->assertRedirect('/login');
        $this->actingAs($this->customer)->get('/business/'.$this->acme->uuid)->assertSee('Message business');

        $this->acme->profile->forceFill(['visibility' => 'private'])->save();
        $this->actingAs($this->customer)->get('/business/'.$this->acme->uuid.'/message')->assertNotFound();
        $this->send()->assertNotFound();
        $this->assertSame(0, Enquiry::count());
    }

    public function test_files_are_seen_only_by_the_people_in_the_conversation(): void
    {
        $this->send(null, ['files' => [UploadedFile::fake()->image('site.jpg', 800, 600)]])->assertRedirect();
        $e = Enquiry::sole();
        $file = Attachment::where('attachable_type', 'enquiry_message')->sole();

        $this->actingAs($this->customer)->get('/attachments/'.$file->uuid)->assertOk();
        $this->actingAs($this->owner)->get('/attachments/'.$file->uuid)->assertOk();
        $this->actingAs($this->editor)->get('/attachments/'.$file->uuid)->assertNotFound();
        $this->actingAs(User::factory()->withPersonalTeam()->create())->get('/attachments/'.$file->uuid)->assertNotFound();

        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid.'/reply'), ['body' => 'Map of the site for the crew.', 'internal' => 1, 'files' => [UploadedFile::fake()->image('map.jpg', 800, 600)]])->assertRedirect();
        $noteFile = Attachment::where('attachable_type', 'enquiry_message')->latest('id')->firstOrFail();
        $this->actingAs($this->customer)->get('/attachments/'.$noteFile->uuid)->assertNotFound();
        $this->actingAs($this->admin)->get('/attachments/'.$noteFile->uuid)->assertOk();
    }

    public function test_a_closed_enquiry_takes_no_more_customer_messages(): void
    {
        $e = $this->enquiry();
        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid), ['status' => 'closed'])->assertRedirect();
        $this->assertNotNull($e->fresh()->closed_at);

        $this->actingAs($this->customer)->get('/inbox/'.$e->uuid)->assertSee('closed this enquiry');
        $this->actingAs($this->customer)->post('/inbox/'.$e->uuid.'/reply', ['body' => 'One more thing please.'])->assertSessionHasErrors('body');
        $this->assertSame(1, $e->messages()->where('kind', EnquiryMessage::CUSTOMER)->count());
    }

    public function test_unread_counts_follow_ups_and_numbers(): void
    {
        $service = app(EnquiryService::class);
        $e = $this->enquiry();

        $this->assertSame(1, $service->unreadCount($this->owner));
        $this->actingAs($this->owner)->get($this->inbox('/'.$e->uuid))->assertOk();
        $this->assertSame(0, $service->unreadCount($this->owner->fresh()));

        $this->travel(3)->hours();
        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid.'/reply'), ['body' => 'Yes, Tuesday.'])->assertRedirect();
        $this->assertSame(1, $service->unreadCount($this->customer));
        $this->actingAs($this->customer)->get('/inbox/'.$e->uuid);
        $this->assertSame(0, $service->unreadCount($this->customer));

        $stats = $service->stats($this->acme);
        $this->assertSame(3.0, $stats['median_first_response_hours']);
        $this->assertSame(1, $stats['last_30_days']);

        // A follow-up date reminds the handlers once, on the day.
        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid), ['follow_up_on' => now()->addDay()->toDateString()])->assertRedirect();
        $this->artisan('business:enquiry-follow-ups')->assertSuccessful();
        $this->assertSame(0, UserNotice::where('kind', UserNotice::ENQUIRY_FOLLOW_UP)->count());
        $this->travel(1)->days();
        $this->artisan('business:enquiry-follow-ups')->assertSuccessful();
        $this->artisan('business:enquiry-follow-ups')->assertSuccessful();
        $this->assertSame(2, UserNotice::where('kind', UserNotice::ENQUIRY_FOLLOW_UP)->count()); // owner and admin, once each
    }

    public function test_emails_are_spaced_out_per_conversation(): void
    {
        $e = $this->enquiry();
        Mail::fake();

        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid.'/reply'), ['body' => 'First answer.'])->assertRedirect();
        $this->actingAs($this->owner)->post($this->inbox('/'.$e->uuid.'/reply'), ['body' => 'And a second thought.'])->assertRedirect();

        Mail::assertSentCount(1);
        $this->assertSame(2, UserNotice::where('user_id', $this->customer->id)->count(), 'the bell still shows each reply');
    }
}
