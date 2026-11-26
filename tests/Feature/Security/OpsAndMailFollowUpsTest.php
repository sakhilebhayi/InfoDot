<?php

namespace Tests\Feature\Security;

use App\Jobs\SendNoticeEmail;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Notifications\EmailAddressChanged;
use App\Services\Knowledge\InAppNotices;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Ops\OpsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** R-72 phase 9: what the independent re-audit found, fixed. */
class OpsAndMailFollowUpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_scheduler_that_stopped_hours_ago_still_makes_ready_red(): void
    {
        // N-1: the heartbeat used to expire after two hours and then read as "never seen", which passed.
        app(OpsCheck::class)->beat();
        $this->getJson('/ready')->assertOk();

        $this->travel(3)->hours();
        $this->assertSame(180, app(OpsCheck::class)->minutesSinceHeartbeat());
        $this->getJson('/ready')->assertStatus(503);
    }

    public function test_a_failed_reviewer_queue_email_can_be_sent_on_the_retry(): void
    {
        // N-2: the half-hour throttle was claimed before sending, so a retry after a failed send found it taken and sent nothing.
        $reviewer = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $reviewer->forceFill(['is_moderator' => true, 'two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Queue Co', 'personal_team' => false]);
        $notice = UserNotice::create(['user_id' => $reviewer->id, 'kind' => UserNotice::BUSINESS_TRUST_QUEUE, 'subject_type' => 'business', 'subject_id' => $team->id, 'ref' => 'btq:1', 'created_at' => now()]);
        $job = new SendNoticeEmail($notice->id);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));
        try {
            $job->handle(app(InAppNotices::class), app(NotificationPreferences::class));
            $this->fail('the send failure reaches the queue so the job is retried');
        } catch (\RuntimeException) {
        }
        $this->assertFalse(Cache::has('notice-mail:'.$reviewer->id.':'.UserNotice::BUSINESS_TRUST_QUEUE), 'the failed send gave the throttle back');
    }

    public function test_the_address_change_mail_does_not_render_a_planted_link_in_the_name(): void
    {
        $html = (string) (new EmailAddressChanged('[Click here](https://evil.example/login)'))->toMail(new User)->render();

        $this->assertStringNotContainsString('href="https://evil.example', $html);
        $this->assertStringNotContainsString('https://evil.example', strip_tags($html));
    }
}
