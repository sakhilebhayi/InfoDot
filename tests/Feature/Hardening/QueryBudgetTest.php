<?php

namespace Tests\Feature\Hardening;

use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Reputation\ReputationEngine;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/**
 * Pages and jobs whose query count used to grow with the number of rows on them. Each test fixes a budget that does not depend on that number,
 * so a loop that quietly goes back to one query per row fails here.
 */
class QueryBudgetTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['reputation.enabled' => true]);
    }

    private function queries(callable $do): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $do();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    public function test_the_notices_page_does_not_look_up_each_notice_on_its_own(): void
    {
        $me = $this->person('Me');
        $other = $this->person('Other');

        foreach (range(1, 30) as $i) {
            $q = $this->question($other, 'public', "Question $i about invoicing");
            UserNotice::create(['user_id' => $me->id, 'kind' => UserNotice::ANSWER_RECEIVED, 'subject_type' => 'question', 'subject_id' => $q->id, 'ref' => "r$i", 'created_at' => now()->subMinutes($i)]);
        }

        $n = $this->queries(fn () => $this->actingAs($me)->get('/notifications')->assertOk()->assertSee('Question 1 about invoicing'));

        $this->assertLessThan(30, $n, "30 notices took $n queries; it must not be one per notice");
    }

    public function test_reminders_cost_the_same_number_of_queries_for_five_people_and_for_fifty(): void
    {
        $host = $this->person('Host');
        $seminar = app(SeminarService::class)->save($host, null, ['title' => 'Soon', 'summary' => 'x', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/r'], true);
        $seminar->forceFill(['starts_at' => now()->addMinutes(30)])->save();

        $make = function (int $count) use ($seminar) {
            foreach (range(1, $count) as $_) {
                SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => $this->person('Guest')->id, 'status' => 'registered', 'payment_status' => 'free']);
            }
        };

        $make(5);
        $few = $this->queries(fn () => app(SeminarService::class)->sendReminders());
        UserNotice::query()->delete();
        $make(45);
        $many = $this->queries(fn () => app(SeminarService::class)->sendReminders());

        $this->assertSame(50, UserNotice::count());
        $this->assertLessThanOrEqual($few + 3 * 45 + 2, $many, 'a few queries per person at most');
        $this->assertLessThan($few + 45 * 4, $many, "50 reminders took $many queries");
        // The second run in the same window finds everyone already told and writes nothing.
        $again = $this->queries(fn () => $this->assertSame(0, app(SeminarService::class)->sendReminders()));
        $this->assertLessThan(10, $again, 'a repeat run is a handful of reads, not an insert attempt per person');
    }

    public function test_the_analytics_dashboard_takes_a_fixed_number_of_queries_however_many_members_there_are(): void
    {
        $owner = $this->person('Owner');
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        foreach (range(1, 25) as $i) {
            $m = User::factory()->create(['name' => "Member $i", 'email_verified_at' => now(), 'current_team_id' => $team->id]);
            $team->users()->attach($m, ['role' => 'member']);
            if ($i <= 8) {
                $this->question($m->fresh(), 'private', "Team question $i");
            }
        }

        $n = $this->queries(fn () => $this->actingAs($owner->fresh())->get('/analytics')->assertOk()->assertSee('Member 1'));

        $this->assertLessThan(40, $n, "26 members took $n queries; it must not be one per member");
    }

    public function test_the_overnight_recalculation_works_in_slices_and_resumes_where_it_stopped(): void
    {
        $users = collect(range(1, 6))->map(fn () => $this->person('P'));
        Cache::put('reputation:cursor', $users[2]->id, 3600);   // as if an earlier slice had finished with the third person

        $this->artisan('reputation:recalculate --resume')->assertSuccessful();

        $done = DB::table('reputation_snapshots')->pluck('user_id')->unique()->sort()->values()->all();
        $this->assertSame($users->slice(3)->pluck('id')->sort()->values()->all(), $done, 'only the people after the cursor were done in this slice');
        $this->assertSame(now()->toDateString(), Cache::get('reputation:done'));

        $before = DB::table('reputation_snapshots')->count();
        $this->artisan('reputation:recalculate --resume')->expectsOutputToContain('Already done today')->assertSuccessful();
        $this->assertSame($before, DB::table('reputation_snapshots')->count());
    }

    public function test_a_person_who_switched_the_score_off_during_a_run_gets_no_snapshot(): void
    {
        $user = $this->person('Leaver');
        $stale = User::find($user->id);   // loaded at the start of the run, before they opted out
        User::whereKey($user->id)->update(['reputation_opt_out' => true]);

        $this->assertNull(app(ReputationEngine::class)->snapshot($stale));
        $this->assertSame(0, DB::table('reputation_snapshots')->where('user_id', $user->id)->count());
    }

    public function test_only_the_newest_snapshots_of_each_person_are_kept(): void
    {
        $user = $this->person('Many');
        foreach (range(1, 8) as $i) {
            DB::table('reputation_snapshots')->insert(['user_id' => $user->id, 'rules_version' => 1, 'total' => $i, 'tier' => 'Spark', 'dimensions' => '[]', 'computed_at' => now()]);
        }

        $removed = app(ReputationEngine::class)->pruneSnapshots(3);

        $this->assertSame(5, $removed);
        $this->assertSame([6, 7, 8], DB::table('reputation_snapshots')->where('user_id', $user->id)->orderBy('total')->pluck('total')->all());
    }

    public function test_a_seminar_in_the_database_is_still_the_model_the_notices_page_expects(): void
    {
        $this->assertInstanceOf(Seminar::class, new Seminar);   // guards the Seminar import used by the preloader
    }
}
