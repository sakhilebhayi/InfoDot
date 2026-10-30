<?php

namespace Tests\Feature\Reputation;

use App\Models\Answer;
use App\Models\ReputationRingFlag;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Reputation\ReputationEngine;
use App\Services\Reputation\RingDetector;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** R-13 ring detection: advisory flags and a score discount only; never a ban, never shown to the people in a ring. */
class RingDetectionTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['services.dopemine.secret' => null, 'reputation.enabled' => true]);
    }

    /** $acceptor asked a public question, $author answered it, and $acceptor accepted the answer (written directly: no rate limits, no events). */
    private function edge(User $acceptor, User $author, int $times = 1, ?\DateTimeInterface $at = null): void
    {
        foreach (range(1, $times) as $_) {
            $q = $this->question($acceptor, 'public', 'Question number '.++$this->seq.' about invoicing');
            Answer::create(['question_id' => $q->id, 'user_id' => $author->id, 'body' => self::GOOD, 'body_hash' => md5('h'.$this->seq), 'status' => 'published'])
                ->forceFill(['accepted_at' => $at ?? now(), 'accepted_by' => $acceptor->id])->save();
        }
    }

    /** @return list<User> */
    private function people(int $n): array
    {
        return array_map(fn ($i) => $this->person('Member '.chr(64 + $i)), range(1, $n));
    }

    /** Everyone accepted everyone else's answer. */
    private function mutual(array $people, int $times = 1): void
    {
        foreach ($people as $a) {
            foreach ($people as $b) {
                $a->is($b) || $this->edge($a, $b, $times);
            }
        }
    }

    private function detect(): array
    {
        return app(RingDetector::class)->detect();
    }

    private function flags(?string $kind = null)
    {
        return ReputationRingFlag::query()->when($kind, fn ($q) => $q->where('kind', $kind))->orderBy('id')->get();
    }

    private function ids(User ...$users): array
    {
        $ids = array_map(fn (User $u) => $u->id, $users);
        sort($ids);

        return $ids;
    }

    // ---- what is flagged -------------------------------------------------------------------------------------------------

    public function test_a_planted_three_cycle_is_flagged_with_sorted_members(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);

        $r = $this->detect();

        $this->assertSame([1, 0], [$r['cycles'], $r['clusters']]);
        $flag = $this->flags()->sole();
        $this->assertSame(['cycle', ReputationRingFlag::OPEN, $this->ids($a, $b, $c), 3], [$flag->kind, $flag->status, $flag->member_ids, $flag->edge_count]);
        $this->assertSame(1.0, $flag->internal_ratio);
        $this->assertNotNull($flag->detected_at);
    }

    public function test_a_planted_four_cycle_is_flagged(): void
    {
        [$a, $b, $c, $d] = $this->people(4);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $d);
        $this->edge($d, $a);

        $this->detect();

        $flag = $this->flags()->sole();
        $this->assertSame(['cycle', $this->ids($a, $b, $c, $d)], [$flag->kind, $flag->member_ids]);
    }

    public function test_a_tight_cluster_of_five_is_flagged_as_a_cluster(): void
    {
        $five = $this->people(5);
        $this->mutual($five);

        $r = $this->detect();

        $this->assertSame(1, $r['clusters']);
        $cluster = $this->flags('cluster')->sole();
        $this->assertSame($this->ids(...$five), $cluster->member_ids);
        $this->assertSame(20, $cluster->edge_count);
        $this->assertSame(1.0, $cluster->internal_ratio);
        $this->assertGreaterThan(0, $r['cycles'], 'a group like this also contains cycles, each flagged on its own member set');
    }

    // ---- what must not be flagged ----------------------------------------------------------------------------------------

    public function test_a_normal_community_where_help_flows_outward_is_not_flagged(): void
    {
        $people = $this->people(12);
        // A few askers accept answers from several helpers; helpers answer for many askers; nobody accepts back.
        foreach ([0, 1, 2] as $asker) {
            foreach ([3, 4, 5, 6, 7, 8] as $helper) {
                $this->edge($people[$asker], $people[$helper]);
            }
        }
        foreach ([9, 10, 11] as $asker) {
            $this->edge($people[$asker], $people[3], 2);
        }

        $r = $this->detect();

        $this->assertSame([0, 0, 0], [$r['cycles'], $r['clusters'], $r['created']]);
        $this->assertSame(0, ReputationRingFlag::count());
    }

    public function test_a_mutual_pair_is_left_to_the_reciprocal_control_not_flagged_as_a_ring(): void
    {
        [$a, $b] = $this->people(2);
        $this->edge($a, $b, 5);
        $this->edge($b, $a, 5);

        $this->detect();

        $this->assertSame(0, ReputationRingFlag::count());
    }

    public function test_a_chain_that_never_closes_is_not_a_cycle(): void
    {
        [$a, $b, $c, $d, $e] = $this->people(5);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $d);
        $this->edge($d, $e);

        $this->assertSame(0, $this->detect()['created']);
    }

    public function test_a_cycle_of_five_is_beyond_what_is_looked_for(): void
    {
        $five = $this->people(5);
        foreach ($five as $i => $p) {
            $this->edge($p, $five[($i + 1) % 5]);
        }

        $this->assertSame(0, $this->detect()['created']);
    }

    public function test_a_group_that_gets_most_of_its_help_from_outside_is_not_a_tight_cluster(): void
    {
        $three = $this->people(3);
        $outsiders = $this->people(3);
        $this->mutual($three, 2);
        foreach ($three as $member) {
            foreach ($outsiders as $o) {
                $this->edge($o, $member, 3);
            }
        }

        $edges = app(RingDetector::class)->edges();

        $this->assertSame([], app(RingDetector::class)->clusters($edges), 'under 80% of what each received came from inside');
    }

    public function test_a_chain_of_mutual_pairs_without_enough_links_is_not_a_cluster(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $a);
        $this->edge($b, $c);
        $this->edge($c, $b);

        $detector = app(RingDetector::class);

        $this->assertSame([], $detector->clusters($detector->edges()), 'connected, but only 4 acceptances and density 0.67');
        $this->assertSame(0, $this->detect()['created']);
    }

    public function test_a_component_larger_than_eight_is_not_a_cluster(): void
    {
        $this->mutual($this->people(9));

        $detector = app(RingDetector::class);

        $this->assertSame([], $detector->clusters($detector->edges()));
    }

    public function test_old_acceptances_fall_out_of_the_window(): void
    {
        [$a, $b, $c] = $this->people(3);
        $old = now()->subDays(181);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a, 1, $old);

        $this->assertSame(0, $this->detect()['created']);

        config(['reputation.rings.window_days' => 365]);
        $this->assertSame(1, $this->detect()['created']);
    }

    public function test_a_person_who_switched_their_score_off_is_never_a_member(): void
    {
        [$a, $b, $c] = $this->people(3);
        $c->forceFill(['reputation_opt_out' => true])->save();
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);

        $this->assertSame(0, $this->detect()['created']);
    }

    public function test_opting_out_deletes_flags_that_name_the_person_and_the_download_lists_them(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        $this->detect();
        $engine = app(ReputationEngine::class);

        $export = $engine->export($a);
        $this->assertCount(1, $export['ring_flags']);
        $this->assertSame(['cycle', 3, 'open'], [$export['ring_flags'][0]['kind'], $export['ring_flags'][0]['members_in_group'], $export['ring_flags'][0]['status']]);
        $this->assertStringNotContainsString($b->name, json_encode($export));
        $this->assertSame([], $engine->export($this->person('Stranger'))['ring_flags']);

        $engine->forget($a);

        $this->assertSame(0, ReputationRingFlag::count(), 'a flag that names the person goes');
    }

    public function test_moderators_are_never_members(): void
    {
        $a = $this->person('A');
        $b = $this->person('B');
        $mod = $this->moderator();
        $this->edge($a, $b);
        $this->edge($b, $mod);
        $this->edge($mod, $a);

        $this->assertSame(0, $this->detect()['created']);
    }

    public function test_self_answers_hidden_work_and_company_only_questions_make_no_edges(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);

        // The closing link is on a company-only question.
        $private = $this->question($c, 'company', 'A private question');
        Answer::create(['question_id' => $private->id, 'user_id' => $a->id, 'body' => self::GOOD, 'body_hash' => 'x1', 'status' => 'published'])
            ->forceFill(['accepted_at' => now(), 'accepted_by' => $c->id])->save();
        $this->assertSame(0, $this->detect()['created']);

        // And as a hidden answer on a public one.
        $q = $this->question($c, 'public', 'A public question');
        Answer::create(['question_id' => $q->id, 'user_id' => $a->id, 'body' => self::GOOD, 'body_hash' => 'x2', 'status' => 'published'])
            ->forceFill(['accepted_at' => now(), 'accepted_by' => $c->id, 'hidden_at' => now(), 'hidden_by' => $this->moderator()->id, 'hidden_reason' => 'Not helpful at all'])->save();
        $this->assertSame(0, $this->detect()['created']);
    }

    // ---- idempotence and review ---------------------------------------------------------------------------------------------

    public function test_detecting_again_changes_nothing_and_keeps_a_moderators_decision(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        $this->detect();
        $flag = $this->flags()->sole();
        $mod = $this->moderator();
        $flag->forceFill(['status' => 'dismissed', 'reviewed_by' => $mod->id, 'review_note' => 'Three colleagues on one project', 'reviewed_at' => now()])->save();

        $again = $this->detect();

        $this->assertSame([0, 1], [$again['created'], $again['refreshed']]);
        $this->assertSame(1, ReputationRingFlag::count());
        $this->assertSame(['dismissed', $mod->id, 'Three colleagues on one project'], [$flag->fresh()->status, $flag->fresh()->reviewed_by, $flag->fresh()->review_note]);
    }

    public function test_the_unique_key_is_the_kind_and_the_member_set(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $row = ['kind' => 'cycle', 'member_ids' => [1, 2, 3], 'member_hash' => ReputationRingFlag::hashOf([3, 1, 2]), 'edge_count' => 3, 'internal_ratio' => 1, 'detected_at' => now()];
        ReputationRingFlag::create($row);
        ReputationRingFlag::create($row);
    }

    // ---- the command ---------------------------------------------------------------------------------------------------------

    public function test_the_command_prints_counts_only_and_flags_the_ring(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);

        $this->artisan('reputation:detect-rings')->expectsOutputToContain('1 cycles and 0 clusters found, 1 new flags')->assertSuccessful();
        $this->artisan('reputation:detect-rings')->expectsOutputToContain('0 new flags, 1 already known')->assertSuccessful();
    }

    public function test_the_command_does_nothing_while_reputation_is_off(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        config(['reputation.enabled' => false]);

        $this->artisan('reputation:detect-rings')->expectsOutputToContain('switched off')->assertSuccessful();

        $this->assertSame(0, ReputationRingFlag::count());
    }

    public function test_the_command_is_scheduled_daily_before_the_recalculation(): void
    {
        $events = collect(app(Schedule::class)->events());
        $detect = $events->first(fn ($e) => str_contains($e->command, 'reputation:detect-rings'));
        $recalc = $events->first(fn ($e) => str_contains($e->command, 'reputation:recalculate'));

        $this->assertNotNull($detect);
        $this->assertSame('10 3 * * *', $detect->expression);
        $this->assertSame('40 3 * * *', $recalc->expression);
    }

    // ---- the score discount ----------------------------------------------------------------------------------------------------

    private function accepted(User $u): float
    {
        return app(ReputationEngine::class)->compute($u)['dimensions']['impact']['evidence']['answer_accepted'];
    }

    public function test_an_open_flag_discounts_acceptances_inside_the_ring_and_a_dismissal_removes_it(): void
    {
        [$a, $b, $c] = $this->people(3);
        $outsider = $this->person('Outsider');
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);   // c accepted a's answer
        $this->edge($outsider, $a);
        $this->assertSame(2.0, $this->accepted($a), 'nothing flagged yet');

        $this->detect();
        $this->assertSame(1.25, $this->accepted($a), 'the ring member counts 0.25, the outsider 1.0');
        $this->assertSame(0.25, $this->accepted($b), 'b was accepted by a, in the same ring');

        $mod = $this->moderator();
        $flag = $this->flags()->sole();
        $this->actingAs($mod)->post(route('moderation.rings.review', $flag), ['decision' => 'dismissed', 'note' => 'Colleagues on one project'])->assertRedirect(route('moderation.rings'));

        $this->assertSame(2.0, $this->accepted($a), 'a dismissed flag has no effect');
        $this->actingAs($mod)->post(route('moderation.rings.review', $flag), ['decision' => 'confirmed', 'note' => 'Looked again, accounts are linked'])->assertRedirect();
        $this->assertSame(1.25, $this->accepted($a), 'a confirmed flag discounts like an open one');
    }

    public function test_the_discount_uses_the_factor_in_config(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        $this->detect();
        $this->assertSame(0.25, $this->accepted($a));

        config(['reputation.rings.discount_factor' => 0.1]);
        $this->assertSame(0.1, $this->accepted($a));

        config(['reputation.rings.enabled' => false]);
        $this->assertSame(1.0, $this->accepted($a));
    }

    public function test_the_lowest_factor_applies_and_the_reciprocal_and_ring_factors_do_not_stack(): void
    {
        $three = $this->people(3);
        $this->mutual($three);   // every pair accepts both ways: reciprocal (0.5) AND in a ring (0.25)
        $this->detect();

        // a was accepted once by each of the other two, each a different asker (decay position 0): 0.25 + 0.25, not 0.125 + 0.125.
        $this->assertSame(0.5, $this->accepted($three[0]));

        $this->flags()->each->delete();
        $this->assertSame(1.0, $this->accepted($three[0]), 'without the ring flag only the reciprocal control applies: 0.5 + 0.5');
    }

    public function test_scores_are_snapshotted_when_a_moderator_decides_and_the_people_are_told_nothing(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        $engine = app(ReputationEngine::class);
        $before = $engine->snapshot($a);
        $this->detect();
        $flag = $this->flags()->sole();

        $this->actingAs($this->moderator())->post(route('moderation.rings.review', $flag), ['decision' => 'confirmed', 'note' => 'Linked accounts'])->assertRedirect();

        $this->assertLessThan($before->total, $engine->snapshot($a)->total);
        $this->assertSame(0, UserNotice::count(), 'nobody is notified');
        $this->assertDatabaseMissing('reward_outbox', ['type' => 'infodot.contribution.flagged']);
    }

    // ---- who can see it -----------------------------------------------------------------------------------------------------------

    public function test_only_moderators_see_the_page_and_it_lists_names_and_counts_but_no_content(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        $this->detect();

        $this->get(route('moderation.rings'))->assertRedirect();
        $this->actingAs($a)->get(route('moderation.rings'))->assertForbidden();
        $this->actingAs($a)->post(route('moderation.rings.review', $this->flags()->sole()), ['decision' => 'dismissed', 'note' => 'I am in it'])->assertForbidden();
        $this->assertSame('open', $this->flags()->sole()->status);

        $html = $this->actingAs($this->moderator())->get(route('moderation.rings'))->assertOk()->getContent();
        $this->assertStringContainsString('Member A', $html);
        $this->assertStringContainsString('Member B', $html);
        $this->assertStringContainsString('Member C', $html);
        $this->assertStringContainsString('3 acceptances between them; 100%', $html);
        $this->assertStringNotContainsString('CIPC', $html, 'no answer text');
    }

    public function test_the_people_in_a_ring_see_no_trace_of_it(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        $this->detect();

        foreach (['/progress', '/user/profile/'.$a->id, '/dashboard'] as $url) {
            $res = $this->actingAs($a)->get($url);
            if ($res->status() === 200) {
                $text = strtolower($res->getContent());
                $this->assertStringNotContainsString('account rings', $text, $url);
                $this->assertStringNotContainsString('flagged', $text, $url);
                $this->assertStringNotContainsString('cluster', $text, $url);
            }
        }
        $this->actingAs($a)->get(route('moderation.rings'))->assertForbidden();
    }

    public function test_the_page_is_not_there_while_reputation_is_off_and_a_decision_needs_a_note(): void
    {
        [$a, $b, $c] = $this->people(3);
        $this->edge($a, $b);
        $this->edge($b, $c);
        $this->edge($c, $a);
        $this->detect();
        $mod = $this->moderator();
        $flag = $this->flags()->sole();

        $this->actingAs($mod)->post(route('moderation.rings.review', $flag), ['decision' => 'dismissed', 'note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($mod)->post(route('moderation.rings.review', $flag), ['decision' => 'banned', 'note' => 'Long enough note'])->assertSessionHasErrors('decision');
        $this->assertSame('open', $flag->fresh()->status);

        config(['reputation.enabled' => false]);
        $this->actingAs($mod)->get(route('moderation.rings'))->assertNotFound();
        $this->actingAs($mod)->post(route('moderation.rings.review', $flag), ['decision' => 'dismissed', 'note' => 'Long enough note'])->assertNotFound();
    }
}
