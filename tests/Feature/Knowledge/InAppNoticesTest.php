<?php

namespace Tests\Feature\Knowledge;

use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\InAppNotices;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** In-app notifications about a person's own work: who is told, who is not, what the page shows and what it hides. */
class InAppNoticesTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['services.dopemine.secret' => null]);
    }

    private function kinds(User $u): array
    {
        return UserNotice::where('user_id', $u->id)->orderBy('id')->pluck('kind')->all();
    }

    public function test_the_asker_is_told_when_someone_else_answers(): void
    {
        $asker = $this->person('Asker');
        $helper = $this->person('Helper');
        $this->answer($this->question($asker), $helper);

        $this->assertSame([UserNotice::ANSWER_RECEIVED], $this->kinds($asker));
        $this->assertSame([], $this->kinds($helper), 'the person who answered is not told about their own act');
    }

    public function test_answering_your_own_question_tells_nobody(): void
    {
        $me = $this->person();
        $this->answer($this->question($me), $me);

        $this->assertSame([], $this->kinds($me));
    }

    public function test_the_author_is_told_when_an_answer_is_accepted_and_the_acceptor_is_not(): void
    {
        $asker = $this->person('Asker');
        $helper = $this->person('Helper');
        $answer = $this->answer($this->question($asker), $helper);
        UserNotice::query()->delete();

        app(AnswerService::class)->accept($answer, $asker);

        $this->assertSame([UserNotice::ANSWER_ACCEPTED], $this->kinds($helper));
        $this->assertSame([], $this->kinds($asker));
    }

    public function test_the_author_is_told_when_a_moderator_verifies_a_solution(): void
    {
        $author = $this->person('Author');
        $mod = $this->moderator();
        $solution = $this->solution($author);

        app(ModerationService::class)->verify($solution, $mod);

        $this->assertSame([UserNotice::SOLUTION_VERIFIED], $this->kinds($author));
        $this->assertSame([], $this->kinds($mod));
    }

    public function test_the_same_fact_is_one_notice(): void
    {
        $asker = $this->person('Asker');
        $helper = $this->person('Helper');
        $answer = $this->answer($this->question($asker), $helper);
        $service = app(InAppNotices::class);

        $this->assertFalse($service->record(UserNotice::ANSWER_RECEIVED, $asker, 'question', $answer->question_id, $answer->reference(), $helper));
        $this->assertCount(1, $this->kinds($asker));
    }

    public function test_nothing_is_recorded_for_a_person_who_switched_notices_off(): void
    {
        $asker = $this->person('Asker');
        $asker->forceFill(['notices_in_app' => false])->save();
        $this->answer($this->question($asker), $this->person('Helper'));

        $this->assertSame([], $this->kinds($asker));
    }

    public function test_the_page_lists_the_notice_in_words_and_the_link_marks_it_read(): void
    {
        $asker = $this->person('Asker');
        $this->answer($this->question($asker, 'public', 'How do I register a clothing brand?'), $this->person('Helper'));

        $page = $this->actingAs($asker)->get('/notifications')->assertOk()->getContent();
        $this->assertStringContainsString('Someone answered your question', $page);
        $this->assertStringContainsString('How do I register a clothing brand?', $page);
        $this->assertStringContainsString('Unread', $page);
        $this->assertStringNotContainsString('Helper', $page, 'no other person is named');

        $notice = UserNotice::where('user_id', $asker->id)->first();
        $this->actingAs($asker)->get('/notifications/'.$notice->id.'/open')->assertRedirect();
        $this->assertNotNull($notice->refresh()->read_at);
        $this->assertSame(0, app(InAppNotices::class)->unreadCount($asker));
    }

    public function test_a_question_that_was_hidden_afterwards_shows_no_title(): void
    {
        $asker = $this->person('Asker');
        $question = $this->question($asker, 'public', 'A very identifiable title');
        $this->answer($question, $this->person('Helper'));
        app(ModerationService::class)->hide($question, $this->moderator(), 'This question breaks the rules of the community.');

        $page = $this->actingAs($asker)->get('/notifications')->assertOk()->getContent();

        // The asker still sees their own hidden question elsewhere on the page; the notice itself must not repeat its title.
        preg_match_all('/<li class="ix-notices__item.*?<\/li>/s', $page, $items);
        $this->assertCount(1, $items[0]);
        $this->assertStringContainsString('no longer available', $items[0][0]);
        $this->assertStringNotContainsString('A very identifiable title', $items[0][0]);
        $this->assertStringNotContainsString('/open', $items[0][0], 'there is nothing to open');
    }

    public function test_nobody_can_open_or_read_somebody_elses_notice(): void
    {
        $asker = $this->person('Asker');
        $this->answer($this->question($asker), $this->person('Helper'));
        $notice = UserNotice::where('user_id', $asker->id)->first();
        $other = $this->person('Other');

        $this->actingAs($other)->get('/notifications/'.$notice->id.'/open')->assertNotFound();
        $this->assertNull($notice->refresh()->read_at);
        $this->actingAs($other)->get('/notifications')->assertOk()->assertDontSee('Someone answered');
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/notifications')->assertRedirect();
        $this->post('/notifications/read-all')->assertRedirect();
    }

    public function test_mark_all_read_and_the_switch_change_only_the_signed_in_person(): void
    {
        $asker = $this->person('Asker');
        $bystander = $this->person('Bystander');
        $this->answer($this->question($asker), $this->person('Helper'));
        $this->answer($this->question($bystander, 'public', 'Another question entirely'), $this->person('Helper2'));

        $this->actingAs($asker)->post('/notifications/read-all')->assertRedirect('/notifications');
        $this->assertSame(0, app(InAppNotices::class)->unreadCount($asker));
        $this->assertSame(1, app(InAppNotices::class)->unreadCount($bystander));

        $this->actingAs($asker)->post('/notifications/preference', ['notices_in_app' => '0'])->assertRedirect('/notifications');
        $this->assertFalse($asker->refresh()->wantsInAppNotices());
        $this->assertTrue($bystander->refresh()->wantsInAppNotices());
        $this->actingAs($asker)->post('/notifications/preference', ['notices_in_app' => 'yes'])->assertSessionHasErrors('notices_in_app');
    }

    public function test_the_menu_shows_the_unread_count(): void
    {
        $asker = $this->person('Asker');
        $this->answer($this->question($asker), $this->person('Helper'));

        $this->actingAs($asker)->get('/notifications')->assertOk()->assertSee('1 unread', false);
    }

    public function test_pruning_removes_old_read_notices_and_keeps_recent_and_unread_ones(): void
    {
        $u = $this->person();
        $make = fn (string $ref, $read, int $age) => UserNotice::create(['user_id' => $u->id, 'kind' => UserNotice::ANSWER_RECEIVED, 'subject_type' => 'question', 'subject_id' => 1, 'ref' => $ref, 'read_at' => $read, 'created_at' => now()->subDays($age)]);
        $make('a', now(), 100);   // read and old: goes
        $make('b', null, 100);    // unread and 100 days old: stays
        $make('c', now(), 10);    // read and recent: stays
        $make('d', null, 400);    // older than a year: goes

        $this->assertSame(2, app(InAppNotices::class)->prune());
        $this->assertSame(['b', 'c'], UserNotice::orderBy('ref')->pluck('ref')->all());
    }
}
