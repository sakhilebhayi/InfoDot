<?php

namespace Tests\Feature\Knowledge;

use App\Models\ModerationAction;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Topic;
use App\Models\User;
use App\Services\Knowledge\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** R-14: the official account's curated library entries are published through the ordinary models, idempotently, fail-closed. */
class KnowledgeSeedTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/knowledge-'.uniqid());
        File::makeDirectory($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function entry(string $key = 'pay-yourself-first', string $title = 'Keep business and personal money apart'): string
    {
        $text = <<<MD
---
key: {$key}
title: {$title}
topics: Money and cash flow | Starting a business
tags: bookkeeping, bank account, records
duration: 2
duration_type: hours
evidence: Industry-recognised best practice; South African requirements from official sources.
---
Mixing business and personal money hides whether the business makes money and makes tax a guess.
This entry shows how to separate the two in an afternoon.

## The problem
An owner pays supplier invoices from a personal card and groceries from the business account, so nobody can say what the business earns.

## Open a business bank account
Take your registration documents to the bank and open an account used for nothing else.

## Sources and further reading
Record keeping - SARS - https://www.sars.gov.za/businesses-and-employers/ - seen 2026-10-30.
Last reviewed: 2026-10-30.
MD;

        $path = $this->dir.'/'.$key.'.md';
        File::put($path, $text);

        return $path;
    }

    public function test_a_valid_entry_is_published_under_the_official_account(): void
    {
        $this->entry();

        $this->artisan('knowledge:seed', ['--dir' => $this->dir])->assertExitCode(0);

        $official = User::where('email', config('knowledge.official.email'))->firstOrFail();
        $this->assertTrue($official->isOfficialAccount());
        $this->assertFalse($official->isModerator(), 'the official account must not verify its own entries');
        $this->assertNotNull($official->email_verified_at);
        $this->assertNotNull($official->current_team_id);

        $solution = Solutions::withoutGlobalScopes()->where('seed_key', 'pay-yourself-first')->firstOrFail();
        $this->assertSame($official->id, $solution->user_id);
        $this->assertSame(Solutions::VISIBILITY_PUBLIC, $solution->visibility);
        $this->assertSame(3, Steps::where('solution_id', $solution->id)->count());
        $this->assertStringContainsString('Evidence:', $solution->solution_description);

        $this->assertSame(['money-and-cash-flow', 'starting-a-business'], $solution->topics()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(2, ModerationAction::where('action', ModerationAction::TOPIC_ADDED)->where('actor_id', $official->id)->count());
    }

    public function test_running_twice_updates_in_place_and_reuses_the_account(): void
    {
        $path = $this->entry();
        $this->artisan('knowledge:seed', ['--dir' => $this->dir])->assertExitCode(0);
        $first = Solutions::withoutGlobalScopes()->where('seed_key', 'pay-yourself-first')->firstOrFail();

        File::put($path, str_replace('Keep business and personal money apart', 'Separate business and personal money', File::get($path)));
        $this->artisan('knowledge:seed', ['--dir' => $this->dir])->assertExitCode(0);

        $this->assertSame(1, Solutions::withoutGlobalScopes()->where('seed_key', 'pay-yourself-first')->count());
        $this->assertSame('Separate business and personal money', $first->fresh()->solution_title);
        $this->assertSame(1, User::where('email', config('knowledge.official.email'))->count());
        $this->assertSame(3, Steps::where('solution_id', $first->id)->count(), 'steps are replaced, not appended');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->entry();
        $topicsBefore = Topic::count(); // migrations seed the platform-support starter topics

        $this->artisan('knowledge:seed', ['--dir' => $this->dir, '--dry' => true])->assertExitCode(0);

        $this->assertSame(0, Solutions::withoutGlobalScopes()->count());
        $this->assertSame(0, User::where('email', config('knowledge.official.email'))->count());
        $this->assertSame($topicsBefore, Topic::count());
    }

    public function test_one_bad_file_stops_the_whole_batch(): void
    {
        $this->entry();
        File::put($this->dir.'/broken-entry.md', "---\nkey: broken-entry\ntitle: x\n---\nNo sources section.\n");

        $this->artisan('knowledge:seed', ['--dir' => $this->dir])->assertExitCode(1);

        $this->assertSame(0, Solutions::withoutGlobalScopes()->count(), 'fail closed: a bad file means nothing is written');
    }

    public function test_a_community_solution_with_the_same_title_is_skipped_unless_forced(): void
    {
        $member = User::factory()->withPersonalTeam()->create();
        $existing = new Solutions;
        $existing->user_id = $member->id;
        $existing->team_id = $member->current_team_id;
        $existing->visibility = Solutions::VISIBILITY_PUBLIC;
        $existing->solution_title = 'Keep business and personal money apart';
        $existing->solution_description = 'A member wrote this first.';
        $existing->tags = 'money';
        $existing->duration = 1;
        $existing->duration_type = 'hours';
        $existing->steps = 0;
        $existing->save();

        $this->entry();
        $this->artisan('knowledge:seed', ['--dir' => $this->dir])->assertExitCode(0);
        $this->assertNull(Solutions::withoutGlobalScopes()->where('seed_key', 'pay-yourself-first')->first());

        $this->artisan('knowledge:seed', ['--dir' => $this->dir, '--force' => true])->assertExitCode(0);
        $this->assertNotNull(Solutions::withoutGlobalScopes()->where('seed_key', 'pay-yourself-first')->first());
        $this->assertSame('A member wrote this first.', $existing->fresh()->solution_description, 'the community solution is untouched');
    }

    public function test_validation_catches_the_important_mistakes(): void
    {
        $seeder = app(KnowledgeSeeder::class);

        $cases = [
            'no front matter' => "Just text.\n",
            'key mismatch' => "---\nkey: other-key\ntitle: T itle\ntopics: Money\ntags: a, b, c\nduration: 1\nduration_type: hours\nevidence: Best practice.\n---\nSummary.\n\n## A\nBody.\n\n## Sources\nhttp://example.com - Last reviewed: 2026-10-30.\n",
            'bad duration type' => "---\nkey: bad-entry\ntitle: T itle\ntopics: Money\ntags: a, b, c\nduration: 1\nduration_type: aeons\nevidence: Best practice.\n---\nSummary.\n\n## A\nBody.\n\n## Sources\nhttp://example.com - Last reviewed: 2026-10-30.\n",
            'no link in sources' => "---\nkey: bad-entry\ntitle: T itle\ntopics: Money\ntags: a, b, c\nduration: 1\nduration_type: hours\nevidence: Best practice.\n---\nSummary.\n\n## A\nBody.\n\n## Sources\nNone. Last reviewed: 2026-10-30.\n",
            'no review date' => "---\nkey: bad-entry\ntitle: T itle\ntopics: Money\ntags: a, b, c\nduration: 1\nduration_type: hours\nevidence: Best practice.\n---\nSummary.\n\n## A\nBody.\n\n## Sources\nhttp://example.com\n",
            'too many topics' => "---\nkey: bad-entry\ntitle: T itle\ntopics: One | Two | Three | Four\ntags: a, b, c\nduration: 1\nduration_type: hours\nevidence: Best practice.\n---\nSummary.\n\n## A\nBody.\n\n## Sources\nhttp://example.com - Last reviewed: 2026-10-30.\n",
        ];

        foreach ($cases as $label => $text) {
            $path = $this->dir.'/bad-entry.md';
            File::put($path, $text);
            $errors = [];
            $this->assertNull($seeder->parseFile($path, $errors), $label.' should fail');
            $this->assertNotEmpty($errors, $label.' should explain itself');
        }
    }

    public function test_every_shipped_entry_validates(): void
    {
        $files = glob(database_path('knowledge/*/*.md')) ?: [];

        $this->assertNotEmpty($files, 'the shipped library has at least one entry');

        $errors = [];
        $entries = app(KnowledgeSeeder::class)->parseAll($files, $errors);

        $this->assertSame([], $errors);
        $this->assertSame(count($files), count($entries));
    }
}
