<?php

namespace Tests\Feature;

use App\Models\Solutions;
use App\Support\Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** R-10/R-24: the landing page shows the REAL researched library — rows from the database, or nothing at all. */
class LandingLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_library_entries_the_section_does_not_render(): void
    {
        $this->get('/')->assertOk()->assertDontSee('A library you can act on');
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
            ->assertSee(route('solutions.view', $entry->id));
    }
}
