<?php

namespace Tests\Feature\Demo;

use App\Listeners\SkipMailToReservedDomains;
use App\Models\Answer;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DemoRecord;
use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Models\LibraryEntry;
use App\Models\ModerationAction;
use App\Models\Questions;
use App\Models\SolutionConfirmation;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Demo\DemoInstaller;
use App\Services\Demo\DemoRegistry;
use App\Services\Knowledge\AnswerService;
use App\Services\Licensing\FinanceCompanies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-49: the demonstration content is created by the application's own code, labelled wherever it shows, kept away
 * from search engines, mail and money, and removable exactly: afterwards every table holds what it held before.
 */
class DemonstrationContentTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = '>Demonstration</span>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        // No outside service may hear of demonstration content: any request at all fails the test that made it.
        Http::preventStrayRequests();
    }

    /** @return array<string, int> every table's row count */
    private function counts(): array
    {
        $counts = [];
        foreach (array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name') as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        ksort($counts);

        return $counts;
    }

    private function install(): void
    {
        $this->artisan('demo:install')->assertSuccessful();
        app(DemoRegistry::class)->flush();
    }

    /** Each sign-in starts a fresh session: the session guard signs out a browser whose stored password hash is someone else's. */
    private function signIn(User $user): void
    {
        $this->flushSession();
        $this->actingAs($user);
    }

    private function person(string $first): User
    {
        return User::where('email', $first.'@demo.infodot.test')->firstOrFail();
    }

    private function company(string $name): Team
    {
        return Team::where('name', $name.' (Demonstration)')->firstOrFail();
    }

    private function realPerson(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false, 'name' => 'Northwind Real']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_install_creates_the_companies_people_and_knowledge_it_lists(): void
    {
        $this->install();

        $people = User::where('email', 'like', '%@demo.infodot.test')->get();
        $this->assertCount(3, $people, 'owner, colleague and outside person');
        $this->assertTrue($people->every(fn (User $u) => $u->email_verified_at !== null), 'every demonstration account is verified');
        $this->assertSame(0, User::where('is_moderator', true)->count(), 'no moderator exists unless it is asked for');
        foreach ($people as $person) {
            $this->assertNotNull($person->profile_photo_path);
            Storage::disk('public')->assertExists($person->profile_photo_path);
            $this->assertGreaterThanOrEqual(1, UserNotice::where('user_id', $person->id)->count(), $person->name.' has a notice');
        }

        $ledger = $this->company('Ledger Works');
        $fieldline = $this->company('Fieldline IT');
        foreach ([$ledger, $fieldline] as $company) {
            $profile = $company->profile;
            $this->assertSame(TeamProfile::PUBLIC, $profile->visibility);
            $this->assertNotEmpty($profile->description);
            $this->assertNotEmpty($profile->business_type);
            $this->assertStringEndsWith('.example.com', (string) parse_url((string) $profile->website, PHP_URL_HOST));
            $this->assertTrue(SkipMailToReservedDomains::reserved((string) $profile->contact_email));
            $this->assertStringStartsWith('+27 00 000', (string) $profile->contact_phone);
            $this->assertGreaterThanOrEqual(1, $company->categories()->count());
            $this->assertGreaterThanOrEqual(3, $company->services()->count());
            $this->assertTrue($company->services->every(fn ($s) => str_starts_with((string) $s->price_note, 'Demonstration price')));
            $this->assertSame(1, $company->projects()->count());
            $this->assertSame(1, $company->locations()->count());
            $this->assertSame(1, $company->announcements()->showing()->count());

            // Logo and cover went through the media pipeline: real attachments with real files.
            foreach (['logo', 'cover'] as $role) {
                $media = $company->currentMedia($role);
                $this->assertInstanceOf(Attachment::class, $media);
                Storage::disk(Attachment::DISK)->assertExists($media->path);
                $this->assertNotEmpty($media->meta['dhash'] ?? null, 'the pipeline fingerprinted the '.$role);
            }
        }
        $this->assertEmpty(array_intersect($ledger->categories->pluck('id')->all(), $fieldline->categories->pluck('id')->all()), 'the two companies are in different categories');
        $this->assertTrue($this->person('sipho')->belongsToTeam($ledger));
        $this->assertFalse($this->person('lerato')->belongsToTeam($ledger));

        $questions = Questions::withoutGlobalScopes()->get();
        $this->assertCount(6, $questions);
        $this->assertSame(1, $questions->where('visibility', Questions::VISIBILITY_COMPANY)->count(), 'one company-only question');
        $this->assertSame(7, Answer::count());
        $this->assertSame(4, Answer::whereNotNull('accepted_at')->count());
        $this->assertSame(4, $questions->where('status', 1)->count(), 'a question is solved exactly when an answer was accepted');

        $solutions = Solutions::withoutGlobalScopes()->get();
        $this->assertCount(4, $solutions);
        $this->assertSame(1, $solutions->where('visibility', Solutions::VISIBILITY_COMPANY)->count(), 'one company-only solution');
        $this->assertSame(18, Steps::count());
        $this->assertSame(2, Comment::count());
        $this->assertSame(1, SolutionConfirmation::count());

        $enquiry = Enquiry::sole();
        $this->assertSame($ledger->id, $enquiry->team_id);
        $this->assertSame($this->person('lerato')->id, $enquiry->user_id);
        $this->assertSame([EnquiryMessage::CUSTOMER, EnquiryMessage::BUSINESS], $enquiry->messages->pluck('kind')->all());
    }

    public function test_content_is_filed_under_topics_that_exist_and_none_are_created(): void
    {
        Topic::create(['slug' => 'money-and-cash-flow', 'name' => 'Money and cash flow', 'active' => true, 'sort_order' => 900]);
        $before = Topic::count();

        $this->install();

        $this->assertSame($before, Topic::count());
        $question = Questions::withoutGlobalScopes()->where('question', 'like', 'How much cash%')->firstOrFail();
        $this->assertContains('Money and cash flow', $question->topics->pluck('name')->all());
    }

    public function test_every_record_is_on_the_register_and_no_number_was_typed_in(): void
    {
        $this->install();
        $registry = app(DemoRegistry::class);

        foreach ([User::class, Questions::class, Solutions::class, Answer::class, Comment::class, Enquiry::class, Attachment::class] as $class) {
            $all = $class::withoutGlobalScopes()->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            $this->assertSame($all, collect($registry->ids($class))->sort()->values()->all(), class_basename($class).': every row is registered');
        }
        $this->assertTrue($registry->isDemoTeam($this->company('Ledger Works')));

        // Nothing is paid out, billed or counted by hand: no ledger event waits, no licence was bought, no score was written.
        $this->assertSame(0, DB::table('reward_outbox')->count());
        $this->assertSame(0, DB::table('reputation_snapshots')->count());
        $this->assertSame(0, DB::table('business_verifications')->count(), 'no demonstration business is "verified"');
        $this->assertSame(0, Solutions::withoutGlobalScopes()->whereNotNull('verified_at')->count());
    }

    public function test_installing_twice_creates_nothing_new(): void
    {
        $this->install();
        $before = $this->counts();
        $hash = $this->person('naledi')->password;

        $this->artisan('demo:install')->expectsOutputToContain('already installed')->assertSuccessful();

        $this->assertSame($before, $this->counts());
        $this->assertSame($hash, $this->person('naledi')->password, 'a second run does not change the passwords');
    }

    public function test_passwords_are_generated_at_run_time_and_rotate_issues_new_ones(): void
    {
        $passwords = app(DemoInstaller::class)->install();
        app(DemoRegistry::class)->flush();

        $this->assertCount(3, $passwords);
        $this->assertCount(3, array_unique($passwords));
        foreach ($passwords as $email => $password) {
            $this->assertGreaterThanOrEqual(20, strlen($password));
            $this->assertTrue(Hash::check($password, User::where('email', $email)->value('password')));
        }

        $before = $this->counts();
        $this->artisan('demo:install', ['--rotate' => true])->expectsOutputToContain('New passwords')->assertSuccessful();

        $this->assertSame($before, $this->counts(), 'rotating creates nothing');
        foreach ($passwords as $email => $password) {
            $this->assertFalse(Hash::check($password, User::where('email', $email)->value('password')), 'the old password stopped working');
        }
    }

    public function test_it_refuses_in_production_without_force(): void
    {
        $before = $this->counts();
        $this->app['env'] = 'production';

        $this->artisan('demo:install')->expectsOutputToContain('--force')->assertFailed();
        $this->assertSame($before, $this->counts());

        $this->artisan('demo:install', ['--force' => true])->assertSuccessful();
        app(DemoRegistry::class)->flush();
        $installed = $this->counts();

        $this->artisan('demo:remove')->expectsOutputToContain('--force')->assertFailed();
        $this->assertSame($installed, $this->counts());

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();
        $this->assertSame($before, $this->counts());
    }

    public function test_an_address_already_used_by_a_real_account_stops_the_install(): void
    {
        User::factory()->create(['email' => 'naledi@demo.infodot.test']);
        $before = $this->counts();

        $this->artisan('demo:install')->expectsOutputToContain('not a demonstration account')->assertFailed();

        $this->assertSame($before, $this->counts());
    }

    public function test_the_marker_shows_on_every_page_that_shows_demonstration_content(): void
    {
        $this->install();
        $ledger = $this->company('Ledger Works');
        $naledi = $this->person('naledi');
        $lerato = $this->person('lerato');
        $viewer = $this->realPerson();

        // Signed out: the business page and the directory.
        $this->get(route('business.show', $ledger))->assertOk()->assertSee(self::MARK, false);
        $this->get(route('businesses.index'))->assertOk()->assertSee('Ledger Works (Demonstration)')->assertSee(self::MARK, false);
        $this->get(route('businesses.index', ['q' => 'bookkeeping']))->assertOk()->assertSee('Ledger Works (Demonstration)')->assertSee(self::MARK, false);

        // A real member reading the knowledge base.
        $question = Questions::withoutGlobalScopes()->where('question', 'like', 'How do I chase%')->firstOrFail();
        $solution = Solutions::withoutGlobalScopes()->where('solution_title', 'like', 'Close the month%')->firstOrFail();

        $this->signIn($viewer);
        $this->get(route('questions'))->assertOk()->assertSee($question->question)->assertSee(self::MARK, false);
        $page = $this->get(route('questions.view', ['qid' => $question->id]))->assertOk()->assertSee(self::MARK, false);
        $this->assertGreaterThanOrEqual(2, substr_count($page->getContent(), self::MARK), 'the question and its answer are both marked');
        $this->get(route('solutions'))->assertOk()->assertSee($solution->solution_title)->assertSee(self::MARK, false);
        $this->get(route('solutions.view', ['id' => $solution->id]))->assertOk()->assertSee(self::MARK, false);

        // A profile opens for the people who share a company with its owner.
        $this->signIn($this->person('sipho'));
        $this->get(route('profile.show', ['id' => $naledi->id]))->assertOk()->assertSee(self::MARK, false);

        // The enquiry, from both sides.
        $enquiry = Enquiry::sole();
        $this->signIn($lerato);
        $this->get(route('inbox'))->assertOk()->assertSee(self::MARK, false);
        $this->get(route('inbox.show', $enquiry))->assertOk()->assertSee(self::MARK, false);
        $this->signIn($naledi);
        $this->get(route('business.inbox', $ledger))->assertOk()->assertSee(self::MARK, false);
        $this->get(route('business.inbox.show', [$ledger, $enquiry]))->assertOk()->assertSee(self::MARK, false);
    }

    public function test_the_library_holds_two_labelled_demonstration_answers_and_loses_them_on_removal(): void
    {
        $this->install();

        // Two accepted, public answers are on the shelf: entries that point at the answers, adopted by nobody real.
        $entries = LibraryEntry::query()->get();
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('answer', $entry->kind);
            $this->assertNull($entry->adopted_by, 'no real moderator is named as having adopted demonstration content');
            $this->assertTrue(app(DemoRegistry::class)->has($entry), 'the entry is on the register');
            $this->assertTrue(app(DemoRegistry::class)->has($entry->answer), 'and so is the answer it points at');
            $this->assertNotNull($entry->answer->accepted_at, 'only an answer its asker accepted is shelved');
        }
        // Nobody is paid and nothing is written in a moderator's name for it.
        $this->assertSame(0, DB::table('moderation_actions')->where('action', ModerationAction::LIBRARY_ADOPTED)->count());
        $this->assertSame(0, DB::table('library_outcomes')->count());

        // The Library page is no longer empty, and every row on it carries the marker.
        $this->signIn($this->realPerson());
        $page = $this->get(route('library'))->assertOk()->assertDontSee('The library is empty so far')->assertSee('How do I chase a late invoice');
        $this->assertSame(2, substr_count($page->getContent(), self::MARK), 'one marker per row');

        $this->artisan('demo:remove')->assertSuccessful();
        $this->assertSame(0, LibraryEntry::query()->count(), 'removal takes the entries with the answers');
        $this->get(route('library'))->assertOk()->assertSee('The library is empty so far');
    }

    public function test_real_content_is_never_marked(): void
    {
        $this->install();
        $real = $this->realPerson();
        $real->currentTeam->profile()->create(['visibility' => TeamProfile::PUBLIC, 'description' => 'A real plumbing business.']);
        $question = Questions::withoutSyncingToSearch(fn () => Questions::create([
            'user_id' => $real->id, 'team_id' => $real->current_team_id, 'visibility' => 'public', 'question' => 'A real question about plumbing?', 'description' => 'Details.',
        ]));

        $this->get(route('business.show', $real->currentTeam))->assertOk()->assertDontSee(self::MARK, false);
        $this->signIn($real);
        $this->get(route('questions.view', ['qid' => $question->id]))->assertOk()->assertDontSee(self::MARK, false);
        $this->get(route('profile.show', ['id' => $real->id]))->assertOk()->assertDontSee(self::MARK, false);
    }

    public function test_demonstration_businesses_are_listed_for_people_but_kept_from_search_engines(): void
    {
        $real = $this->realPerson();
        $real->currentTeam->profile()->create(['visibility' => TeamProfile::PUBLIC, 'description' => 'A real plumbing business.']);
        $this->install();
        Cache::forget('sitemap.xml');

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString(route('business.show', $real->currentTeam), $sitemap, 'a real public business is in the sitemap');
        foreach (['Ledger Works', 'Fieldline IT'] as $name) {
            $company = $this->company($name);
            $this->assertStringNotContainsString((string) $company->uuid, $sitemap);

            $this->get(route('business.show', $company))->assertOk()
                ->assertSee('<meta name="robots" content="noindex">', false)
                ->assertDontSee('rel="canonical"', false);
        }

        $this->get(route('business.show', $real->currentTeam))->assertOk()
            ->assertSee('rel="canonical"', false)->assertDontSee('<meta name="robots" content="noindex">', false);
    }

    public function test_nothing_is_mailed_to_a_demonstration_account(): void
    {
        $this->install();

        // The enquiry and its reply each try to mail the other side: both addresses are reserved, so nothing left.
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());

        // And the rule is general: a reserved address never receives, a real one does.
        Mail::raw('Hello', fn ($m) => $m->to('lerato@demo.infodot.test')->subject('To a reserved domain'));
        Mail::raw('Hello', fn ($m) => $m->to('hello@ledgerworks.infodot.test')->subject('To a reserved domain'));
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());

        Mail::raw('Hello', fn ($m) => $m->to('owner@infodot.co.za')->subject('To a real domain'));
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());

        foreach (['a@b.test' => true, 'A@Sub.Domain.TEST' => true, 'a@x.example' => true, 'a@host.invalid' => true, 'a@gmail.com' => false, 'a@contest.co.za' => false, 'a@test.co.za' => false, 'not-an-address' => false] as $address => $reserved) {
            $this->assertSame($reserved, SkipMailToReservedDomains::reserved($address), $address);
        }
    }

    public function test_remove_restores_every_table_to_its_count_and_deletes_the_files(): void
    {
        // A site with its own life: a real person, their business and their question stay exactly as they were.
        $real = $this->realPerson();
        Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $real->id, 'team_id' => $real->current_team_id, 'visibility' => 'public', 'question' => 'A real question?', 'description' => 'Details.']));
        Topic::create(['slug' => 'money-and-cash-flow', 'name' => 'Money and cash flow', 'active' => true, 'sort_order' => 900]);

        $before = $this->counts();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->install();

        $this->assertNotSame($before, $this->counts());
        $this->assertCount(4, Storage::disk('local')->allFiles(), 'two logos and two covers');
        $this->assertCount(3, Storage::disk('public')->allFiles(), 'three profile pictures');

        $this->artisan('demo:remove')->expectsOutputToContain('removed')->assertSuccessful();

        $this->assertSame($before, $this->counts(), 'every table holds what it held before the install');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, DemoRecord::count());
        $this->assertNotNull($real->fresh());
        $this->assertSame(1, Questions::withoutGlobalScopes()->count());

        // And it can be installed again afterwards.
        app(DemoRegistry::class)->flush();
        $this->install();
        $this->assertSame(6 + 1, Questions::withoutGlobalScopes()->count());
    }

    public function test_what_a_demonstration_account_writes_later_is_marked_and_removed_with_the_rest(): void
    {
        $real = $this->realPerson();
        $realQuestion = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $real->id, 'team_id' => $real->current_team_id, 'visibility' => 'public', 'question' => 'A real question?', 'description' => 'Details.']));
        $before = $this->counts();
        $this->install();

        // During a walkthrough the demonstration owner answers a real person's question.
        $naledi = $this->person('naledi');
        $this->signIn($naledi);
        $answer = app(AnswerService::class)->publish($realQuestion, $naledi, 'A demonstration answer, written while showing the product to someone.');

        $this->assertTrue(app(DemoRegistry::class)->has($answer));
        $this->assertSame(0, DB::table('reward_outbox')->count(), 'a demonstration account earns no Dot Points');
        $this->signIn($real);
        $this->get(route('questions.view', ['qid' => $realQuestion->id]))->assertOk()->assertSee(self::MARK, false);

        // What a real person writes is never put on the register, whatever is installed.
        $own = app(AnswerService::class)->publish($realQuestion, $real, 'My own follow-up to my own question, as a real person.');
        $this->assertFalse(app(DemoRegistry::class)->has($own));

        $this->app['auth']->forgetGuards();
        $this->artisan('demo:remove')->assertSuccessful();

        $this->assertNull(Answer::withTrashed()->find($answer->id));
        $this->assertNotNull(Answer::find($own->id), 'the real person\'s answer is untouched');
        $this->assertNotNull($realQuestion->fresh());
        // The notice about the demonstration answer went with it; the real person's own answer left one event for the ledger.
        $after = $this->counts();
        foreach (['answers' => 1, 'reward_outbox' => 1] as $table => $more) {
            $this->assertSame($before[$table] + $more, $after[$table], $table);
            $after[$table] = $before[$table];
        }
        unset($before['user_achievements'], $after['user_achievements']);
        $this->assertSame($before, $after);
    }

    public function test_remove_stops_when_a_real_account_added_to_demonstration_content(): void
    {
        $this->install();
        $real = $this->realPerson();
        $question = Questions::withoutGlobalScopes()->where('question', 'like', 'How much cash%')->firstOrFail();
        $this->signIn($real);
        $theirs = app(AnswerService::class)->publish($question, $real, 'A real person joins in on the demonstration question with their view.');
        $this->app['auth']->forgetGuards();
        $installed = $this->counts();

        $this->artisan('demo:status')->expectsOutputToContain('1 in answers')->assertSuccessful();
        $this->artisan('demo:remove')->expectsOutputToContain('1 in answers')->assertFailed();
        $this->assertSame($installed, $this->counts(), 'nothing was removed');
        $this->assertNotNull(Answer::find($theirs->id));

        $this->artisan('demo:remove', ['--with-attached' => true])->assertSuccessful();

        $this->assertNull(Answer::withTrashed()->find($theirs->id), 'an answer cannot outlive its question');
        $this->assertNotNull($real->fresh(), 'the real account itself is never touched');
        $this->assertNotNull($real->currentTeam->fresh());
        $this->assertSame(0, Questions::withoutGlobalScopes()->count());
        $this->assertSame(0, User::where('email', 'like', '%@demo.infodot.test')->count());
    }

    public function test_demonstration_accounts_and_companies_are_kept_away_from_money(): void
    {
        $this->install();
        $ledger = $this->company('Ledger Works');
        $naledi = $this->person('naledi');

        // Dot.Finance is configured and would be told about any real company; a demonstration one is never sent.
        config(['licensing.finance_connected' => true, 'licensing.finance.url' => 'https://finance.invalid', 'licensing.finance.secret' => str_repeat('s', 40)]);
        Http::fake();
        $this->assertFalse(app(FinanceCompanies::class)->sync($ledger));
        $this->assertFalse(app(FinanceCompanies::class)->sync($ledger, true));
        Http::assertNothingSent();
        // The same call for a real company does go out: the rule, not a missing setting, is what stopped it.
        $real = $this->realPerson();
        $this->assertTrue(app(FinanceCompanies::class)->sync($real->currentTeam));
        Http::assertSent(fn ($request) => str_contains($request->body(), (string) $real->currentTeam->uuid));

        // Payout set-up, the seller terms and spending Dot Points are closed to the account itself.
        $this->signIn($naledi);
        $this->post(route('points.redeem'), ['ticket' => str_repeat('a', 32)])->assertForbidden();
        $this->post(route('seminars.payouts.store'), ['business_name' => 'Ledger Works', 'settlement_bank' => '001', 'account_number' => '000000000'])->assertForbidden();
        $this->post(route('seminars.payouts.seller'), ['trading_name' => 'Ledger Works'])->assertForbidden();
        $this->post(route('seminars.payouts.agreement'), ['accept' => 1])->assertForbidden();
        Http::assertNotSent(fn ($request) => str_contains($request->body(), (string) $ledger->uuid) || str_contains($request->url(), 'payout'));

        // A real account is not stopped by that rule (it gets the route's own answer, not the refusal).
        $this->signIn($real);
        $this->assertNotSame(403, $this->post(route('points.redeem'), ['ticket' => str_repeat('a', 32)])->getStatusCode());
    }

    public function test_a_comment_is_indexed_by_its_words_only(): void
    {
        $author = $this->realPerson();
        $question = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $author->id, 'visibility' => 'public', 'question' => 'A question?', 'description' => 'Details.']));

        // Saved the way the comment box saves it: with the author attached. The index must still get plain values.
        $comment = $question->comments()->make(['body' => 'A comment with words to find.']);
        $comment->user()->associate($author);
        $comment->save();

        $this->assertSame(['id' => $comment->id, 'body' => 'A comment with words to find.'], $comment->toSearchableArray());
    }

    public function test_the_moderator_is_created_only_when_asked_for_and_removed_either_way(): void
    {
        $before = $this->counts();

        // Asked for at install: four accounts, one of them a moderator, and status says so.
        $this->artisan('demo:install', ['--with-moderator' => true])->expectsOutputToContain('real moderation powers')->assertSuccessful();
        app(DemoRegistry::class)->flush();
        $moderator = $this->person('thabo');
        $this->assertTrue($moderator->isModerator());
        $this->assertTrue(app(DemoRegistry::class)->isDemoUser($moderator));
        $this->assertNotNull($moderator->email_verified_at);
        $this->assertSame(4, User::where('email', 'like', '%@demo.infodot.test')->count());
        $this->assertSame(1, User::where('is_moderator', true)->count());
        $this->assertSame(6, Questions::withoutGlobalScopes()->count(), 'the content is the same with or without the moderator');
        $this->artisan('demo:status')->expectsOutputToContain('thabo@demo.infodot.test exists')->assertSuccessful();

        $this->artisan('demo:remove')->assertSuccessful();
        $this->assertSame($before, $this->counts());
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Left out by default, then added later: only the moderator is created, and the other passwords stay as they were.
        app(DemoRegistry::class)->flush();
        $this->artisan('demo:install')->expectsOutputToContain('No demonstration moderator was created')->assertSuccessful();
        app(DemoRegistry::class)->flush();
        $this->artisan('demo:status')->expectsOutputToContain('Demonstration moderator: none')->assertSuccessful();
        $hash = $this->person('naledi')->password;
        $questions = Questions::withoutGlobalScopes()->count();

        $this->artisan('demo:install', ['--with-moderator' => true])->expectsOutputToContain('moderator was added')->assertSuccessful();
        app(DemoRegistry::class)->flush();
        $this->assertTrue($this->person('thabo')->isModerator());
        $this->assertSame($hash, $this->person('naledi')->password);
        $this->assertSame($questions, Questions::withoutGlobalScopes()->count());

        // Asking again changes nothing.
        $installed = $this->counts();
        $this->artisan('demo:install', ['--with-moderator' => true])->expectsOutputToContain('already installed')->assertSuccessful();
        $this->assertSame($installed, $this->counts());

        $this->artisan('demo:remove')->assertSuccessful();
        $this->assertSame($before, $this->counts());
        $this->assertSame(0, User::where('is_moderator', true)->count());
    }

    public function test_status_reports_what_is_there(): void
    {
        $this->artisan('demo:status')->expectsOutputToContain('No demonstration content')->assertSuccessful();
        $this->artisan('demo:remove')->expectsOutputToContain('Nothing was changed')->assertSuccessful();

        $this->install();

        $this->artisan('demo:status')
            ->expectsOutputToContain('naledi@demo.infodot.test')
            ->expectsOutputToContain('Nothing a real account wrote is attached')
            ->assertSuccessful();
    }
}
