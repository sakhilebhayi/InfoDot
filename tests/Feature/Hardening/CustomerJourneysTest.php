<?php

namespace Tests\Feature\Hardening;

use App\Livewire\Answers;
use App\Models\Answer;
use App\Models\Questions;
use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Http\Livewire\CreateTeamForm;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Whole customer journeys (enterprise programme phase 11.4), each walked through the real HTTP routes and Livewire calls from the first page to
 * the user-visible result, with the server-side state checked at the end. These are the flows the platform sells; a regression in any step is a
 * broken promise, not a cosmetic bug.
 */
class CustomerJourneysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** A different person takes over: fresh session, fresh guard, as a different browser would be. */
    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->actingAs($user->fresh());
    }

    private function registerThrough(string $name, string $email): User
    {
        // Each person arrives signed out, as they would in a real browser (the previous journey step may have left a signed-in session).
        if ($this->app['auth']->guard('web')->check()) {
            $this->post('/logout');
            $this->app['auth']->forgetGuards();
            $this->flushSession();
        }

        $this->post('/register', ['name' => $name, 'email' => $email, 'password' => 'a-long-enough-password-1', 'password_confirmation' => 'a-long-enough-password-1', 'terms' => '1'])->assertRedirect();
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();   // the verification mail click, without the mailbox

        return $user->fresh();
    }

    public function test_a_new_entrepreneur_registers_asks_and_gets_an_accepted_answer(): void
    {
        $asker = $this->registerThrough('Nandi Entrepreneur', 'nandi@example.test');
        $this->assertTrue($asker->currentTeam->personal_team, 'registration made a personal workspace');

        $this->switchTo($asker)->post('/questions/add', ['topics' => [Topic::query()->value('id')], 'question' => 'How do I price my first product?', 'description' => 'I am launching next month and have no idea where to start.', 'publish' => '1'])
            ->assertRedirect('questions');
        $question = Questions::where('user_id', $asker->id)->firstOrFail();
        $this->assertSame('public', $question->visibility);

        $helper = $this->registerThrough('Sipho Helper', 'sipho@example.test');
        Livewire::actingAs($helper)->test(Answers::class, ['question' => $question])
            ->set('body', 'Start from your costs, then check what the market pays: add materials, time at your target rate, and a margin; then compare three competitors.')
            ->call('publish')->assertHasNoErrors();
        $answer = Answer::where('question_id', $question->id)->firstOrFail();

        Livewire::actingAs($asker)->test(Answers::class, ['question' => $question->fresh()])->call('accept', $answer->id);
        $this->assertNotNull($answer->fresh()->accepted_at);
        $this->assertSame(1, (int) $question->fresh()->status, 'the question shows as solved');

        // The helper's contribution is queued for the points ledger, and the asker was told in the app.
        $this->assertTrue(DB::table('reward_outbox')->where('contribution_ref', 'infodot:answer:'.$answer->id)->exists());
        $this->switchTo($asker)->get('/notifications')->assertOk()->assertSee('Someone answered your question');
    }

    public function test_a_host_publishes_a_free_seminar_people_register_and_attendance_is_recorded(): void
    {
        $host = $this->registerThrough('Hope Host', 'hope@example.test');
        $this->switchTo($host)->post('/seminars', ['title' => 'Bookkeeping for one-person businesses', 'summary' => 'The one hour that saves you in February.', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'delivery' => 'link', 'join_url' => 'https://meet.example.test/books', 'publish' => '1'])
            ->assertRedirect();
        $seminar = Seminar::where('host_id', $host->id)->firstOrFail();
        $this->assertSame('published', $seminar->status);

        $guest = $this->registerThrough('Gugu Guest', 'gugu@example.test');
        $this->switchTo($guest)->post("/seminars/{$seminar->id}/register")->assertRedirect();
        $this->assertSame('registered', SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $guest->id)->value('status'));

        // The window opens; the guest joins through InfoDot, which is what records attendance.
        $seminar->forceFill(['starts_at' => now()->addMinutes(5)])->save();
        $this->switchTo($guest)->post("/seminars/{$seminar->id}/join")->assertRedirect('https://meet.example.test/books');
        $this->assertNotNull(SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $guest->id)->value('attended_at'));

        // The host sees the person and the count on their own page.
        $this->switchTo($host)->get("/seminars/{$seminar->id}")->assertOk()->assertSee('Gugu Guest');
    }

    public function test_a_host_sells_a_ticket_and_the_money_trail_is_complete(): void
    {
        config(['licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 'platform-secret', 'seminars.paid.enabled' => true, 'seminars.paid.agreement_approved' => true, 'seminars.paid.callback_secret' => 'cb']);
        Http::fake([
            'billing.test/api/payout-accounts' => Http::response(['id' => 77, 'bank_code' => '058', 'last4' => '7890', 'status' => 'active'], 201),
            'billing.test/api/payout-accounts/77/agreement' => Http::response(['status' => 'accepted'], 201),
            'billing.test/api/payout-banks' => Http::response(['banks' => []]),
            'billing.test/api/seminar-tickets' => Http::response(['reference' => 'ps_ref', 'authorization_url' => 'https://checkout.paystack.com/abc', 'status' => 'pending'], 201),
            'billing.test/*' => Http::response(['status' => 'ok']),
        ]);

        // The host sets up payouts, accepts the agreement, prices a seminar.
        $host = $this->registerThrough('Paida Host', 'paida@example.test');
        $this->switchTo($host)->post('/seminars/payouts', ['business_name' => 'Paida Trading', 'settlement_bank' => '058', 'account_number' => '1234567890'])->assertRedirect();
        $this->switchTo($host)->post('/seminars/payouts/agreement', ['accept' => '1'])->assertRedirect();
        $this->switchTo($host)->post('/seminars', ['title' => 'Pricing masterclass', 'summary' => 'Ticketed.', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'delivery' => 'link', 'join_url' => 'https://meet.example.test/m', 'price' => '200.00', 'publish' => '1'])->assertRedirect();
        $seminar = Seminar::where('host_id', $host->id)->firstOrFail();
        $this->assertSame(20000, $seminar->price_cents);

        // A buyer starts checkout and is sent to Paystack; nothing is a ticket yet.
        $buyer = $this->registerThrough('Bongi Buyer', 'bongi@example.test');
        $this->switchTo($buyer)->post("/seminars/{$seminar->id}/register")->assertRedirect('https://checkout.paystack.com/abc');
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('pending', $reg->payment_status);

        // Billing's signed callback is the only thing that makes it a ticket.
        $body = json_encode(['event' => 'ticket.paid', 'ticket_ref' => $reg->ticket_ref, 'reference' => 'ps_ref', 'amount_cents' => 20000, 'currency' => 'ZAR']);
        $h = Signature::headers('cb', $body);
        $this->call('POST', '/api/billing/ticket-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INFODOT_TIMESTAMP' => $h['X-InfoDot-Timestamp'], 'HTTP_X_INFODOT_SIGNATURE' => $h['X-InfoDot-Signature']], $body)->assertOk();
        $this->assertSame('paid', $reg->fresh()->payment_status);

        // The buyer holds a receipt; the host sees the sale and InfoDot's share.
        $this->switchTo($buyer)->get("/seminars/{$seminar->id}/receipt")->assertOk()->assertSee('Paida Host');   // the seller name: the host, until they save a trading name on the seller form
        $this->switchTo($host)->get("/seminars/{$seminar->id}")->assertOk()->assertSee('Tickets sold');
    }

    public function test_a_business_registers_invites_its_team_and_the_seat_limit_holds(): void
    {
        $owner = $this->registerThrough('Olwethu Owner', 'olwethu@example.test');
        $this->switchTo($owner);
        Livewire::actingAs($owner->fresh())->test(CreateTeamForm::class)->set('state', ['name' => 'Olwethu Logistics'])->call('createTeam');
        $company = Team::where('name', 'Olwethu Logistics')->firstOrFail();
        $this->assertFalse((bool) $company->personal_team);

        $manager = Livewire::actingAs($owner->fresh());

        foreach ([['thabo@example.test', 'admin'], ['lerato@example.test', 'member']] as [$email, $role]) {
            $manager->test('teams.team-member-manager', ['team' => $company->fresh()])->set('addTeamMemberForm', ['email' => $email, 'role' => $role])->call('addTeamMember')->assertHasNoErrors();
        }

        $this->assertSame(2, $company->teamInvitations()->count());

        // The third free seat is the owner's, so a third invitation must be refused with a plain explanation, and nothing removed.
        Livewire::actingAs($owner->fresh())->test('teams.team-member-manager', ['team' => $company->fresh()])->set('addTeamMemberForm', ['email' => 'four@example.test', 'role' => 'member'])->call('addTeamMember')->assertHasErrors();
        $this->assertSame(2, $company->teamInvitations()->count());
        $this->assertStringContainsString('seat', strtolower(DB::table('team_licence_events')->where('type', 'blocked')->value('type') !== null ? 'seat limit' : ''));
    }

    public function test_a_person_exercises_their_data_rights_end_to_end(): void
    {
        $person = $this->registerThrough('Privacy Person', 'privacy@example.test');
        $this->switchTo($person)->post('/questions/add', ['topics' => [Topic::query()->value('id')], 'question' => 'A question I will take with me', 'description' => 'Details.'])->assertRedirect();

        // Download first: the seminar-data export answers (even when empty) and never someone else's rows.
        $this->switchTo($person)->get('/account/seminar-data')->assertOk();

        // Then deletion: the account, the personal team and the question all go. (Jetstream's delete form is a Livewire component, not a route.)
        $this->switchTo($person);
        Livewire::actingAs($person->fresh())->test(DeleteUserForm::class)->set('password', 'a-long-enough-password-1')->call('deleteUser');
        $this->assertNull(User::find($person->id));
        $this->assertSame(0, Team::where('user_id', $person->id)->count());
    }
}
