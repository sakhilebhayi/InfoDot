<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\VerifySolution;
use App\Models\ModerationAction;
use App\Models\Solutions;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

class SolutionVerificationTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    public function test_a_moderator_verifies_a_solution_once_and_it_is_recorded(): void
    {
        $author = $this->person();
        $mod = $this->moderator('Checker');
        $solution = $this->solution($author);
        $service = app(ModerationService::class);

        $this->assertTrue($service->verify($solution, $mod, 'Followed every step on a real filing.'));
        $this->assertFalse($service->verify($solution, $this->moderator('Another')), 'verifying twice changes nothing');

        $fresh = Solutions::find($solution->id);
        $this->assertTrue($fresh->isVerified());
        $this->assertSame($mod->id, $fresh->verified_by);
        $this->assertSame(['verified'], ModerationAction::pluck('action')->all());
        $this->assertSame('Followed every step on a real filing.', ModerationAction::first()->reason);
        $this->assertSame($mod->id, $fresh->verifier->id);
    }

    public function test_the_author_never_verifies_their_own_solution_even_as_a_moderator_and_members_cannot_at_all(): void
    {
        $authorMod = $this->moderator('Author mod');
        $own = $this->solution($authorMod);
        $someone = $this->solution($this->person());

        foreach ([[$own, $authorMod], [$someone, $this->person('Member')]] as [$solution, $who]) {
            try {
                app(ModerationService::class)->verify($solution, $who);
                $this->fail('the wrong person verified');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame(0, ModerationAction::count());
        $this->assertFalse(Solutions::find($own->id)->isVerified());
    }

    public function test_removing_verification_needs_a_reason_and_is_recorded(): void
    {
        $solution = $this->solution($this->person());
        $service = app(ModerationService::class);
        $mod = $this->moderator();
        $service->verify($solution, $mod);

        try {
            $service->unverify($solution, $mod, '');
            $this->fail('removed without a reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }
        $this->assertTrue(Solutions::find($solution->id)->isVerified());

        $this->assertTrue($service->unverify($solution, $mod, 'Step 3 is out of date since the rules changed.'));
        $this->assertFalse($service->unverify($solution, $mod, 'Step 3 is out of date since the rules changed.'));

        $this->assertFalse(Solutions::find($solution->id)->isVerified());
        $this->assertSame(['verified', 'unverified'], ModerationAction::orderBy('id')->pluck('action')->all());
    }

    public function test_a_hidden_solution_cannot_be_verified(): void
    {
        $solution = $this->solution($this->person());
        $service = app(ModerationService::class);
        $service->hide($solution, $this->moderator('Hider'), 'Off topic for this community.');

        $this->expectException(ValidationException::class);
        $service->verify($solution->fresh(), $this->moderator('Checker'));
    }

    public function test_the_badge_shows_to_everyone_and_the_buttons_only_to_other_moderators(): void
    {
        $author = $this->person('Author');
        $reader = $this->person('Reader');
        $mod = $this->moderator('Checker');
        $solution = $this->solution($author);

        $this->actingAs($reader)->get('/solution/view/'.$solution->id)->assertOk()->assertDontSee('Verified by moderator')->assertDontSee('Verify this solution');
        $this->actingAs($mod)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('Verify this solution');

        Livewire::actingAs($mod)->test(VerifySolution::class, ['solutionId' => $solution->id])->call('verify')->assertSee('Verified by moderator')->assertSee('Remove verification');

        foreach ([$reader, $author] as $viewer) {
            $this->actingAs($viewer)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('Verified by moderator')->assertSee('Checker')->assertDontSee('Remove verification');
        }
        $this->actingAs($reader)->get('/solutions')->assertOk()->assertSee('Verified by moderator');
    }

    public function test_the_verify_component_refuses_a_member_or_the_author_who_calls_it_by_hand(): void
    {
        $author = $this->person();
        $solution = $this->solution($author);

        Livewire::actingAs($this->person('Member'))->test(VerifySolution::class, ['solutionId' => $solution->id])->call('verify')->assertForbidden();
        Livewire::actingAs($author)->test(VerifySolution::class, ['solutionId' => $solution->id])->call('verify')->assertForbidden();
        $mod = $this->moderator();
        $component = Livewire::actingAs($mod)->test(VerifySolution::class, ['solutionId' => $solution->id])->call('unverify')->assertHasErrors('note');

        $this->assertSame(0, ModerationAction::count());
        $this->assertSame(0, DB::table('solutions')->whereNotNull('verified_at')->count());
    }

    public function test_verification_is_queued_for_the_points_ledger_as_one_event(): void
    {
        Http::fake();
        $solution = $this->solution($this->person());

        app(ModerationService::class)->verify($solution, $this->moderator());

        $this->assertSame(['infodot.solution.verified'], DB::table('reward_outbox')->pluck('type')->all());
    }
}
