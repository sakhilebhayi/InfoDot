<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\Moderation\Topics as TopicDesk;
use App\Models\ModerationAction;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Topic;
use App\Models\User;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\TopicService;
use App\Support\Knowledge\TopicCatalogue;
use App\Support\Knowledge\TopicStats;
use App\Support\Platforms;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\Feature\Knowledge\Concerns\MakesTopics;
use Tests\TestCase;

/**
 * Topics: the curated list, who manages it, tagging when asking and writing, moderators' corrections, and browsing by
 * topic with counts that never include company-only or hidden work.
 */
class TopicsTest extends TestCase
{
    use MakesCommunity;
    use MakesTopics;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.dopemine.secret' => null]);
    }

    private function ask(User $as, array $overrides = [])
    {
        return $this->actingAs($as)->post('/questions/add', $overrides + [
            'question' => 'How do I set up a recurring invoice?',
            'description' => 'I send the same invoice every month and want it automatic.',
            'topics' => [$this->topic('billing')->id],
            'publish' => '1',
        ]);
    }

    /** A second company's member, who can see public work only. */
    private function outsider(): User
    {
        return $this->person('Outsider');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The list, the migration and the links
    // ---------------------------------------------------------------------------------------------------------------

    public function test_the_starting_list_has_the_cross_cutting_topics_and_one_per_visible_platform(): void
    {
        foreach (['getting-started', 'sign-in-and-accounts', 'billing-and-payments', 'data-and-reports', 'integrations'] as $slug) {
            $this->assertTrue(Topic::where('slug', $slug)->exists(), $slug);
        }

        $this->assertSame('Sign-in & accounts', $this->topic('sign-in-and-accounts')->name);

        foreach (Platforms::visible() as $key => $platform) {
            $this->assertSame($platform['name'], $this->topic((string) $key)->name, "platform $key has a topic");
        }

        foreach (Platforms::INTERNAL as $key) {
            $this->assertFalse(Topic::where('slug', $key)->exists(), "$key is an internal bridge service, not a subject");
        }

        $this->assertSame(0, Topic::where('active', false)->count());
        $this->assertSame(0, DB::table('question_topic')->count() + DB::table('solution_topic')->count(), 'nothing is tagged by guesswork');
    }

    public function test_running_the_migrations_and_the_seed_again_changes_nothing_and_keeps_moderator_edits(): void
    {
        $mod = $this->moderator();
        $service = app(TopicService::class);
        $service->rename($this->topic('billing'), $mod, 'Money in and out');
        $service->setActive($this->topic('tasks'), $mod, false);
        $before = Topic::orderBy('id')->get(['id', 'slug', 'name', 'active', 'sort_order'])->toArray();

        $this->assertSame(0, TopicCatalogue::seed());
        foreach (['2026_10_25_100001_create_topics_tables', '2026_10_25_100002_add_topic_suggestions_to_users_table', '2026_10_25_100003_seed_initial_topics'] as $file) {
            (require database_path("migrations/$file.php"))->up();
        }

        $this->assertSame($before, Topic::orderBy('id')->get(['id', 'slug', 'name', 'active', 'sort_order'])->toArray());
    }

    public function test_a_question_can_have_each_topic_once_and_the_links_go_when_the_question_does(): void
    {
        $question = $this->file($this->question($this->person()), 'billing');

        try {
            DB::table('question_topic')->insert(['question_id' => $question->id, 'topic_id' => $this->topic('billing')->id]);
            $this->fail('the same pair was stored twice');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('question_topic')->count());
        }

        DB::table('questions')->where('id', $question->id)->delete();
        $this->assertSame(0, DB::table('question_topic')->count());
    }

    public function test_a_topic_that_is_in_use_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $this->file($this->question($this->person()), 'billing');

        try {
            $this->topic('billing')->delete();
            $this->fail('a topic in use was deleted');
        } catch (\LogicException) {
            $this->assertTrue(Topic::where('slug', 'billing')->exists());
        }

        $this->assertTrue($this->topic('integrations')->delete());
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Managing the list: moderators only, every change in the trail
    // ---------------------------------------------------------------------------------------------------------------

    public function test_only_moderators_open_the_topic_desk(): void
    {
        $this->get('/moderation/topics')->assertRedirect();
        $this->actingAs($this->person())->get('/moderation/topics')->assertForbidden();
        $this->actingAs($this->moderator())->get('/moderation/topics')->assertOk()->assertSee('Add a topic')->assertSee('Needs a topic');
        Livewire::actingAs($this->person())->test(TopicDesk::class)->assertForbidden();
        $this->actingAs($this->moderator())->get('/moderation')->assertOk()->assertSee(route('moderation.topics'), false);
    }

    public function test_a_moderator_adds_a_topic_and_it_is_recorded(): void
    {
        $mod = $this->moderator('Curator');

        Livewire::actingAs($mod)->test(TopicDesk::class)
            ->set('newName', '  Delivery   and returns ')->set('newDescription', 'Couriers, collections and refunds.')
            ->call('add')->assertHasNoErrors();

        $topic = Topic::where('slug', 'delivery-and-returns')->firstOrFail();
        $this->assertSame('Delivery and returns', $topic->name);
        $this->assertTrue($topic->active);

        $row = ModerationAction::firstOrFail();
        $this->assertSame([$mod->id, 'topic_added', $topic->getMorphClass(), $topic->id], [$row->actor_id, $row->action, $row->subject_type, $row->subject_id]);
        $this->assertSame($mod->uuid, $row->actor_uuid);
        $this->assertSame('Topic added', $row->label());
    }

    public function test_a_topic_needs_a_real_unused_name(): void
    {
        $mod = $this->moderator();
        $desk = Livewire::actingAs($mod)->test(TopicDesk::class);

        $desk->set('newName', 'ab')->call('add')->assertHasErrors('newName');
        $desk->set('newName', '!!!')->call('add')->assertHasErrors('newName');
        $desk->set('newName', 'BILLING & payments')->call('add')->assertHasErrors('newName');
        $desk->set('newName', 'Dot.Tasks')->call('add')->assertHasErrors('newName');

        $this->assertSame(0, ModerationAction::count());
    }

    public function test_renaming_keeps_the_address_records_the_change_and_repeating_it_does_nothing(): void
    {
        $mod = $this->moderator();
        $billing = $this->topic('billing');

        Livewire::actingAs($mod)->test(TopicDesk::class)
            ->set('names.'.$billing->id, 'Money in and out')->call('save', $billing->id)->assertHasNoErrors();

        $fresh = $billing->fresh();
        $this->assertSame(['billing', 'Money in and out'], [$fresh->slug, $fresh->name]);
        $this->assertStringContainsString('“Dot.Billing” to “Money in and out”', ModerationAction::firstOrFail()->reason);

        $this->assertFalse(app(TopicService::class)->rename($fresh, $mod, 'Money in and out', $fresh->description));
        $this->assertSame(1, ModerationAction::count());
        $this->actingAs($this->person())->get('/topics/billing')->assertOk()->assertSee('Money in and out');
    }

    public function test_switching_a_topic_off_and_on_is_recorded_once_each_and_the_last_one_cannot_be_switched_off(): void
    {
        $mod = $this->moderator();
        $service = app(TopicService::class);
        $integrations = $this->topic('integrations');

        $this->assertTrue($service->setActive($integrations, $mod, false));
        $this->assertFalse($service->setActive($integrations, $mod, false), 'already off');
        $this->assertTrue($service->setActive($integrations, $mod, true));
        $this->assertFalse($service->setActive($integrations, $mod, true), 'already on');
        $this->assertSame(['topic_deactivated', 'topic_activated'], ModerationAction::orderBy('id')->pluck('action')->all());

        Topic::where('slug', '!=', 'integrations')->update(['active' => false]);
        $this->expectException(ValidationException::class);
        $service->setActive($integrations, $mod, false);
    }

    public function test_members_cannot_change_the_list_even_by_calling_the_service(): void
    {
        $member = $this->person();
        $service = app(TopicService::class);

        foreach ([
            fn () => $service->create($member, 'Anything'),
            fn () => $service->rename($this->topic('billing'), $member, 'Renamed'),
            fn () => $service->setActive($this->topic('billing'), $member, false),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('a member changed the topic list');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame(0, ModerationAction::count());
        $this->assertSame('Dot.Billing', $this->topic('billing')->name);
    }

    public function test_a_demoted_moderator_loses_the_desk_at_once(): void
    {
        $mod = $this->moderator();
        $desk = Livewire::actingAs($mod)->test(TopicDesk::class)->set('newName', 'Warehousing');
        $mod->forceFill(['is_moderator' => false])->save();

        $desk->call('add')->assertForbidden();
        $this->assertFalse(Topic::where('slug', 'warehousing')->exists());
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Tagging when asking and writing
    // ---------------------------------------------------------------------------------------------------------------

    public function test_the_ask_form_offers_the_active_topics_only_and_can_start_from_one(): void
    {
        app(TopicService::class)->setActive($this->topic('integrations'), $this->moderator(), false);

        $html = $this->actingAs($this->person())->get('/questions/ask?topic=billing')->assertOk()->getContent();

        $this->assertStringContainsString('name="topics[]"', $html);
        $this->assertStringContainsString('Billing &amp; payments', $html);
        $this->assertStringNotContainsString('Integrations', $html);
        $this->assertMatchesRegularExpression('/value="'.$this->topic('billing')->id.'"/', $html);
    }

    public function test_asking_files_the_question_under_one_to_three_topics(): void
    {
        $asker = $this->person();

        foreach ([['billing'], ['billing', 'tasks'], ['billing', 'tasks', 'hr']] as $i => $slugs) {
            $this->ask($asker, ['question' => "Question number $i for the topics", 'topics' => array_map(fn ($s) => $this->topic($s)->id, $slugs)])->assertRedirect('questions');

            $this->assertEqualsCanonicalizing($slugs, Questions::where('question', "Question number $i for the topics")->firstOrFail()->topics->pluck('slug')->all());
        }
    }

    public function test_asking_without_a_valid_topic_is_refused_and_nothing_is_saved(): void
    {
        $asker = $this->person();
        $inactive = $this->topic('integrations');
        app(TopicService::class)->setActive($inactive, $this->moderator(), false);
        $ids = fn (string ...$s) => array_map(fn ($x) => $this->topic($x)->id, $s);

        foreach ([
            'none chosen' => [[], 'topics'],
            'missing' => [null, 'topics'],
            'four chosen' => [$ids('billing', 'tasks', 'hr', 'files'), 'topics'],
            'unknown id' => [[999999], 'topics.0'],
            'switched off' => [[$inactive->id], 'topics.0'],
            'not a number' => [['billing'], 'topics.0'],
            'the same twice' => [$ids('billing', 'billing'), 'topics.0'],
        ] as $label => [$topics, $errorOn]) {
            $payload = ['question' => 'A question that should not be saved', 'description' => 'Details that are long enough.'];
            if ($topics !== null) {
                $payload['topics'] = $topics;
            }

            $this->actingAs($asker)->post('/questions/add', $payload)->assertSessionHasErrors($errorOn);
        }

        $this->assertSame(0, Questions::withoutGlobalScopes()->count());
        $this->assertSame(0, DB::table('question_topic')->count());
    }

    public function test_the_ask_form_shows_the_reason_when_a_topic_is_missing(): void
    {
        $this->actingAs($this->person())->from('/questions/ask')
            ->post('/questions/add', ['question' => 'No topic here', 'description' => 'Details that are long enough.'])
            ->assertRedirect('/questions/ask')->assertSessionHasErrors('topics');

        $this->followingRedirects()->actingAs($this->person())->from('/questions/ask')
            ->post('/questions/add', ['question' => 'No topic here', 'description' => 'Details that are long enough.'])
            ->assertSee('Choose at least one topic');
    }

    public function test_solutions_are_filed_under_topics_the_same_way(): void
    {
        $author = $this->person();
        $base = [
            'solution_title' => 'Send a recurring invoice', 'solution_description' => 'Set it once.', 'tags-input' => 'invoice, billing',
            'duration' => 1, 'duration_type' => 'hours', 'solution_heading' => ['Open billing'], 'solution_body' => ['Go to invoices.'],
        ];

        $this->actingAs($author)->get('/solution/create?topic=tasks')->assertOk()->assertSee('name="topics[]"', false);
        $this->actingAs($author)->post('/solution/add', $base)->assertSessionHasErrors('topics');
        $this->actingAs($author)->post('/solution/add', $base + ['topics' => [$this->topic('billing')->id, $this->topic('tasks')->id, $this->topic('hr')->id, $this->topic('files')->id]])->assertSessionHasErrors('topics');
        $this->assertSame(0, Solutions::withoutGlobalScopes()->count());

        $this->actingAs($author)->post('/solution/add', $base + ['topics' => [$this->topic('billing')->id]])->assertRedirect(route('solutions'));
        $this->assertSame(['billing'], Solutions::firstOrFail()->topics->pluck('slug')->all());
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Untagged work and moderators' corrections
    // ---------------------------------------------------------------------------------------------------------------

    public function test_old_work_is_shown_as_untagged_and_not_guessed(): void
    {
        $asker = $this->person();
        $question = $this->question($asker);
        $solution = $this->solution($asker);

        $this->actingAs($asker)->get('/question/view/'.$question->id)->assertOk()->assertSee('Untagged');
        $this->actingAs($asker)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('Untagged');
        $this->actingAs($asker)->get('/questions')->assertOk()->assertSee('Untagged');
        $this->assertSame(0, $question->topics()->count());
    }

    public function test_a_moderator_corrects_the_topics_of_a_question_and_the_trail_says_what_changed(): void
    {
        $question = $this->file($this->question($this->person()), 'billing');
        $mod = $this->moderator('Sorter');

        $this->actingAs($mod)->get('/question/view/'.$question->id)->assertOk()->assertSee('Correct topics');

        $this->actingAs($mod)->post(route('questions.topics', ['qid' => $question->id]), [
            'topics' => [$this->topic('tasks')->id, $this->topic('hr')->id], 'note' => 'This is about work, not money.',
        ])->assertRedirect()->assertSessionHas('topics_notice', 'Topics updated.');

        $this->assertEqualsCanonicalizing(['tasks', 'hr'], $question->fresh()->topics->pluck('slug')->all());

        $row = ModerationAction::firstOrFail();
        $this->assertSame([$mod->id, 'topics_set', $question->getMorphClass(), $question->id], [$row->actor_id, $row->action, $row->subject_type, $row->subject_id]);
        $this->assertSame('From: Dot.Billing. To: Dot.HR, Dot.Tasks. This is about work, not money.', $row->reason);

        // Repeating it is a no-op: nothing is written.
        $this->actingAs($mod)->post(route('questions.topics', ['qid' => $question->id]), ['topics' => [$this->topic('tasks')->id, $this->topic('hr')->id]])
            ->assertSessionHas('topics_notice', 'Those were already the topics.');
        $this->assertSame(1, ModerationAction::count());
    }

    public function test_a_moderator_corrects_a_solution_too(): void
    {
        $solution = $this->solution($this->person());
        $mod = $this->moderator();

        $this->actingAs($mod)->post(route('solutions.topics', ['id' => $solution->id]), ['topics' => [$this->topic('billing')->id]])->assertRedirect();

        $this->assertSame(['billing'], $solution->fresh()->topics->pluck('slug')->all());
        $this->assertSame('From: none. To: Dot.Billing.', ModerationAction::firstOrFail()->reason);
    }

    public function test_corrections_are_refused_for_members_for_own_work_for_other_companies_and_for_bad_choices(): void
    {
        $author = $this->person('Author');
        $question = $this->file($this->question($author), 'billing');
        $payload = ['topics' => [$this->topic('tasks')->id]];

        // A member and the question's own author: no.
        $this->actingAs($author)->post(route('questions.topics', ['qid' => $question->id]), $payload)->assertForbidden();
        $this->actingAs($this->person())->post(route('questions.topics', ['qid' => $question->id]), $payload)->assertForbidden();

        // A moderator, but it is their own question: another moderator has to.
        $ownMod = $this->moderator('Own');
        $own = $this->file($this->question($ownMod), 'billing');
        $this->actingAs($ownMod)->post(route('questions.topics', ['qid' => $own->id]), $payload)->assertForbidden();
        $this->actingAs($ownMod)->get('/question/view/'.$own->id)->assertOk()->assertDontSee('Correct topics');

        // Another company's private question does not exist for this moderator.
        $private = $this->question($author, 'company');
        $this->actingAs($this->moderator('Elsewhere'))->post(route('questions.topics', ['qid' => $private->id]), $payload)->assertNotFound();

        // Bad choices.
        $mod = $this->moderator('Checker');
        $this->actingAs($mod)->post(route('questions.topics', ['qid' => $question->id]), ['topics' => []])->assertSessionHasErrors('topics');
        $this->actingAs($mod)->post(route('questions.topics', ['qid' => $question->id]), ['topics' => array_map(fn ($s) => $this->topic($s)->id, ['billing', 'tasks', 'hr', 'files'])])->assertSessionHasErrors('topics');
        $this->actingAs($mod)->post(route('questions.topics', ['qid' => $question->id]), ['topics' => [999999]])->assertSessionHasErrors('topics');

        $this->assertSame(['billing'], $question->fresh()->topics->pluck('slug')->all());
        $this->assertSame(0, ModerationAction::count());
    }

    public function test_a_switched_off_topic_can_be_kept_but_not_newly_chosen(): void
    {
        $mod = $this->moderator();
        $question = $this->file($this->question($this->person()), 'integrations');
        app(TopicService::class)->setActive($this->topic('integrations'), $mod, false);
        $service = app(TopicService::class);

        $this->assertTrue($service->correct($question, $mod, [$this->topic('integrations')->id, $this->topic('billing')->id]), 'kept, with one more');

        $other = $this->question($this->person());
        $this->expectException(ValidationException::class);
        $service->correct($other, $mod, [$this->topic('integrations')->id]);
    }

    public function test_the_needs_a_topic_list_shows_untagged_work_that_the_moderator_can_file(): void
    {
        $mod = $this->moderator();
        $author = $this->person();
        $untagged = $this->question($author, 'public', 'Untagged and waiting');
        $this->file($this->question($author, 'public', 'Already filed away'), 'billing');
        $this->question($mod, 'public', 'The moderator wrote this one');
        $this->question($this->person('Other company'), 'company', 'Private to another company');
        $solution = $this->solution($author, 'public', 'An untagged procedure');

        $desk = Livewire::actingAs($mod)->test(TopicDesk::class)->set('tab', 'untagged');
        $desk->assertSee('Untagged and waiting')->assertDontSee('Already filed away')->assertDontSee('The moderator wrote this one')->assertDontSee('Private to another company');
        $this->assertSame(1, $desk->viewData('untaggedQuestionCount'));
        $this->assertSame(1, $desk->viewData('untaggedSolutionCount'));

        $desk->set('picks.q-'.$untagged->id, [(string) $this->topic('tasks')->id, (string) $this->topic('hr')->id])->call('assign', 'q-'.$untagged->id)->assertHasNoErrors();
        $desk->assertDontSee('Untagged and waiting');
        $this->assertEqualsCanonicalizing(['tasks', 'hr'], $untagged->fresh()->topics->pluck('slug')->all());

        $desk->set('kind', 'solution')->assertSee('An untagged procedure')
            ->set('picks.s-'.$solution->id, [(string) $this->topic('billing')->id])->call('assign', 's-'.$solution->id)->assertHasNoErrors();
        $this->assertSame(['billing'], $solution->fresh()->topics->pluck('slug')->all());
        $this->assertSame(2, ModerationAction::where('action', 'topics_set')->count());
    }

    public function test_filing_from_the_needs_a_topic_list_needs_one_to_three_topics(): void
    {
        $mod = $this->moderator();
        $question = $this->question($this->person());
        $desk = Livewire::actingAs($mod)->test(TopicDesk::class)->set('tab', 'untagged');

        $desk->call('assign', 'q-'.$question->id)->assertHasErrors('picks.q-'.$question->id);
        // The browser sends a single tick as true/false until the property is an array; the desk starts each row as one.
        $this->assertSame([], $desk->get('picks')['q-'.$question->id]);
        $desk->set('picks.q-'.$question->id, false)->call('assign', 'q-'.$question->id)->assertHasErrors('picks.q-'.$question->id);
        $desk->set('picks.q-'.$question->id, array_map(fn ($s) => (string) $this->topic($s)->id, ['billing', 'tasks', 'hr', 'files']))
            ->call('assign', 'q-'.$question->id)->assertHasErrors('picks.q-'.$question->id);
        $this->assertSame(0, $question->topics()->count());
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Browsing, filtering and searching by topic
    // ---------------------------------------------------------------------------------------------------------------

    public function test_the_questions_list_filters_by_topic_and_by_untagged(): void
    {
        $asker = $this->person();
        $this->file($this->question($asker, 'public', 'Invoice question'), 'billing');
        $this->file($this->question($asker, 'public', 'Task question'), 'tasks');
        $this->question($asker, 'public', 'Old untagged question');
        $reader = $this->outsider();

        // The header search also lists the newest questions, so the lists are read from what the page was given.
        $titles = fn (string $url) => $this->actingAs($reader)->get($url)->assertOk()->viewData('questions')->pluck('question')->sort()->values()->all();

        $this->assertSame(['Invoice question'], $titles('/questions?topic=billing'));
        $this->assertSame(['Old untagged question'], $titles('/questions?topic=none'));
        $this->assertSame(['Invoice question', 'Old untagged question', 'Task question'], $titles('/questions'));
        $this->assertSame([], $titles('/questions?topic=no-such-topic'));
        $this->actingAs($reader)->get('/questions?topic=no-such-topic')->assertSee('There is no topic with that address');
        $this->actingAs($reader)->get('/questions?topic=hr')->assertOk()->assertSee('No questions under Dot.HR yet');
    }

    public function test_the_solutions_list_filters_by_topic(): void
    {
        $author = $this->person();
        $this->file($this->solution($author, 'public', 'Invoice procedure'), 'billing');
        $this->solution($author, 'public', 'Untagged procedure');

        $reader = $this->outsider();
        $titles = fn (string $url) => $this->actingAs($reader)->get($url)->assertOk()->viewData('solutions')->pluck('solution_title')->all();

        $this->assertSame(['Invoice procedure'], $titles('/solutions?topic=billing'));
        $this->assertSame(['Untagged procedure'], $titles('/solutions?topic=none'));
        $this->actingAs($this->outsider())->get('/solutions?topic=hr')->assertOk()->assertSee('No solutions under Dot.HR yet');
    }

    public function test_company_only_and_hidden_work_follows_the_usual_rules_in_every_list(): void
    {
        $asker = $this->person('Asker');
        $mod = $this->moderator();
        $private = $this->file($this->question($asker, 'company', 'Our private supplier question'), 'billing');
        $hidden = $this->file($this->question($asker, 'public', 'A hidden question'), 'billing');
        app(ModerationService::class)->hide($hidden, $mod, 'Hidden for the test.');
        $reader = $this->outsider();

        $this->assertSame([], $this->actingAs($reader)->get('/questions?topic=billing')->viewData('questions')->all());
        $this->assertSame([], $this->actingAs($reader)->get('/topics/billing')->viewData('questions')->all());
        $this->assertSame([], $this->actingAs($reader)->get('/solution-results?topic=billing')->viewData('results')['questions']->items());

        // The author still sees their own, in the lists; nobody else's company ever does.
        $mine = fn ($url) => $this->actingAs($asker)->get($url)->viewData('questions')->pluck('question')->sort()->values()->all();
        $this->assertSame(['A hidden question', 'Our private supplier question'], $mine('/topics/billing'));
        $this->assertSame(['A hidden question', 'Our private supplier question'], $mine('/questions?topic=billing'));
        $this->assertNotNull($private);
    }

    public function test_search_accepts_a_topic_as_a_filter(): void
    {
        $author = $this->person();
        $this->file($this->question($author, 'public', 'How do I register a billing address?'), 'billing');
        $this->file($this->question($author, 'public', 'How do I register a task board?'), 'tasks');
        $this->file($this->solution($author, 'public', 'Register a billing contact'), 'billing');
        $reader = $this->outsider();

        $found = function (string $url) use ($reader) {
            $results = $this->actingAs($reader)->get($url)->assertOk()->viewData('results');

            return [collect($results['questions']->items())->pluck('question')->sort()->values()->all(), collect($results['solutions']->items())->pluck('solution_title')->all()];
        };

        $this->assertSame([['How do I register a billing address?', 'How do I register a task board?'], ['Register a billing contact']], $found('/solution-results?search=register'));
        $this->assertSame([['How do I register a billing address?'], ['Register a billing contact']], $found('/solution-results?search=register&topic=billing'));
        // A topic and no words lists the whole topic.
        $this->assertSame([['How do I register a task board?'], []], $found('/solution-results?topic=tasks'));
        $this->assertSame([[], []], $found('/solution-results?search=register&topic=nonsense'));
        $this->actingAs($reader)->get('/solution-results?search=register&topic=nonsense')->assertSee('There is no topic with that address');
    }

    public function test_the_topic_index_counts_public_work_only_and_the_same_numbers_for_everyone(): void
    {
        $asker = $this->person('Asker');
        $mod = $this->moderator();
        $helper = $this->person('Helper');

        $open = $this->file($this->question($asker, 'public', 'Open public one'), 'billing');
        $answered = $this->file($this->question($asker, 'public', 'Answered public one'), 'billing');
        $this->answer($answered, $helper);
        $this->file($this->question($asker, 'company', 'Company only one'), 'billing');
        $hidden = $this->file($this->question($asker, 'public', 'Hidden public one'), 'billing');
        app(ModerationService::class)->hide($hidden, $mod, 'Hidden for the test.');
        $this->file($this->solution($asker, 'public', 'Public procedure'), 'billing');
        $this->file($this->solution($asker, 'company', 'Company procedure'), 'billing');
        $this->assertNotNull($open);

        $expected = ['questions' => 2, 'answered' => 1, 'unanswered' => 1, 'solutions' => 1];
        $this->assertSame($expected, TopicStats::forTopic($this->topic('billing')->id));

        // Same numbers whether the reader is the author (who can see more), a moderator or a stranger.
        foreach ([$asker, $mod, $this->outsider()] as $reader) {
            $this->actingAs($reader);
            $this->assertSame($expected, TopicStats::forTopic($this->topic('billing')->id));
            $html = $this->get('/topics')->assertOk()->assertSee('Dot.Billing')->getContent();
            $this->assertMatchesRegularExpression('/Dot\.Billing.*?data-label="Questions">2<.*?data-label="Answered">1<.*?data-label="Unanswered">1<.*?data-label="Solutions">1</s', $html);
        }

        $this->actingAs($asker)->get('/topics/billing')->assertOk()->assertSee('Public questions')->assertSee('can also include work that is not public');
        $page = $this->actingAs($this->outsider())->get('/topics/billing')->assertOk()->assertDontSee('can also include work that is not public');
        $this->assertEqualsCanonicalizing(['Open public one', 'Answered public one'], $page->viewData('questions')->pluck('question')->all());
    }

    public function test_a_solved_question_is_answered_and_a_self_answer_or_hidden_answer_is_not(): void
    {
        $asker = $this->person('Asker');
        $mod = $this->moderator();
        $solved = $this->file($this->question($asker), 'tasks');
        $solved->forceFill(['status' => 1])->save();
        $selfAnswered = $this->file($this->question($asker), 'tasks');
        $this->answer($selfAnswered, $asker);
        $hiddenAnswer = $this->file($this->question($asker), 'tasks');
        $answer = $this->answer($hiddenAnswer, $this->person('Helper'));
        app(ModerationService::class)->hide($answer, $mod, 'Hidden for the test.');

        $counts = TopicStats::forTopic($this->topic('tasks')->id);
        $this->assertSame(['questions' => 3, 'answered' => 1, 'unanswered' => 2, 'solutions' => 0], $counts);
    }

    public function test_the_topic_pages_need_a_signed_in_person_and_an_unknown_topic_is_not_found(): void
    {
        $this->get('/topics')->assertRedirect();
        $this->get('/topics/billing')->assertRedirect();
        $this->actingAs($this->person())->get('/topics/not-a-topic')->assertNotFound();
        $this->actingAs($this->person())->get('/topics')->assertOk()->assertSee('Older work stays untagged');
    }

    public function test_question_lists_and_pages_show_their_topics_as_links(): void
    {
        $asker = $this->person();
        $question = $this->file($this->question($asker), 'billing', 'tasks');

        $html = $this->actingAs($asker)->get('/question/view/'.$question->id)->assertOk()->getContent();
        $this->assertStringContainsString('href="'.url('/topics/billing').'"', $html);
        $this->assertStringContainsString('href="'.url('/topics/tasks').'"', $html);
        $this->actingAs($asker)->get('/questions')->assertOk()->assertSee('Dot.Billing')->assertSee('Dot.Tasks');
    }
}
