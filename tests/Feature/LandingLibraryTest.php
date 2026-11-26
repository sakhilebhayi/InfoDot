<?php

namespace Tests\Feature;

use App\Models\Solutions;
use App\Models\User;
use App\Support\Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/** R-10/R-24: the landing page shows the REAL researched library — rows from the database, or nothing at all. */
class LandingLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_library_entries_the_section_does_not_render(): void
    {
        $this->get('/')->assertOk()->assertDontSee('A library you can act on')->assertDontSee('data-hd-panel', false);
    }

    public function test_the_landing_page_shows_real_library_entries_with_their_references(): void
    {
        $dir = storage_path('framework/testing/landing-lib-'.uniqid());
        File::makeDirectory($dir, 0755, true);
        File::put($dir.'/cash-basics.md', <<<'MD'
---
key: cash-basics
title: Keep cash under control from week one
topics: Money and cash flow
tags: cash, control
duration: 2
duration_type: weeks
evidence: Industry-recognised best practice from official sources.
---
A short summary of the method.

## Step one — Open the books
Write down what came in and what went out, every week.

## Sources and further reading
Cash guidance — Example Publisher — https://example.org/cash — 1 January 2026.
Last reviewed: 2026-10-31.
MD);

        $this->artisan('knowledge:seed', ['--dir' => $dir])->assertExitCode(0);
        File::deleteDirectory($dir);

        $entry = Solutions::withoutGlobalScopes()->where('seed_key', 'cash-basics')->firstOrFail();

        $this->get('/')->assertOk()
            ->assertSee('A library you can act on')
            ->assertSee('Keep cash under control from week one')
            ->assertSee(Reference::for($entry))
            ->assertSee(route('solutions.view', $entry->id))
            // R-72 (F-05): a visitor is told before following a link that reading asks them to sign in.
            ->assertSee('Reading one asks you to sign in first');
    }

    public function test_the_hero_panel_shows_a_real_entry_and_its_own_steps_as_example_tasks(): void
    {
        $dir = storage_path('framework/testing/landing-panel-'.uniqid());
        File::makeDirectory($dir, 0755, true);
        File::put($dir.'/hand-over.md', <<<'MD'
---
key: hand-over
title: Write down a job so someone else can finish it
topics: Operations
tags: hand-over
duration: 1
duration_type: weeks
evidence: Industry-recognised best practice from official sources.
---
A short summary of the method.

## The problem
Jobs stall when the one person who knows is away.

## Who this is for
Owners of small teams.

## Step 1 — List what only you know
Note the contacts, the dates and the places.

## Step 2 — Write the steps
One line for each action.

## Step 3 — Test it
Ask someone to follow the page.

## Sources and further reading
Hand-over guidance — Example Publisher — https://example.org/handover — 1 January 2026.
Last reviewed: 2026-10-31.
MD);

        $this->artisan('knowledge:seed', ['--dir' => $dir])->assertExitCode(0);
        File::deleteDirectory($dir);

        $entry = Solutions::withoutGlobalScopes()->where('seed_key', 'hand-over')->firstOrFail();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-hd-panel', $html);
        $this->assertStringContainsString('Write down a job so someone else can finish it', $html);
        $this->assertStringContainsString(Reference::for($entry), $html);
        // The tasks are the entry's own steps, and the layout says it is an example.
        $this->assertStringContainsString('List what only you know', $html);
        $this->assertStringContainsString('The same steps as tasks', $html);
        // Framing sections are not tasks: only the numbered steps are listed, without their numbers.
        $panel = Str::between($html, 'data-hd-panel', '</aside>');
        $this->assertStringNotContainsString('The problem', $panel);
        $this->assertStringNotContainsString('Who this is for', $panel);
        $this->assertStringNotContainsString('Step 1', $panel);
        $this->assertStringContainsString('Write the steps', $panel);
        $this->assertStringContainsString('Example', $html);
        // R-72 (F-05): a visitor is told that reading it asks them to sign in; a signed-in person is not.
        $this->assertStringContainsString('Reading it asks you to sign in; the account is free.', $panel);
        $this->assertStringContainsString('sign in to read', $html);
        $signedIn = $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get('/')->getContent();
        $this->assertStringNotContainsString('Reading it asks you to sign in', $signedIn);
    }
}
