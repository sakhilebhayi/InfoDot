<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\BusinessAppeal;
use App\Models\BusinessInvestigation;
use App\Models\BusinessInvestigationEvent;
use App\Models\BusinessReport;
use App\Models\BusinessVerification;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * R-33 Phase 4a / R-29 Part D: reports about businesses, investigations, restrictions and appeals.
 * A report changes nothing by itself; only a reviewer outside the business investigates; the
 * public never sees a label; the business is told and may appeal once to a different reviewer.
 */
class BusinessTrustTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $editor;

    private User $reporter;

    private User $reviewer;

    private User $reviewer2;

    private Team $acme;

    private const DETAIL = 'This page uses the logo and registration number of Acme Water, a different company in Durban.';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->editor = User::factory()->withPersonalTeam()->create();
        $this->reporter = User::factory()->withPersonalTeam()->create(['name' => 'Rita Reporter']);
        $this->reviewer = User::factory()->withPersonalTeam()->create(['name' => 'First Reviewer']);
        $this->reviewer->forceFill(['is_moderator' => true])->save();
        $this->reviewer2 = User::factory()->withPersonalTeam()->create(['name' => 'Second Reviewer']);
        $this->reviewer2->forceFill(['is_moderator' => true])->save();

        $this->acme = Team::forceCreate(['user_id' => $this->owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $this->owner->switchTeam($this->acme);
        $this->acme->profileOrNew()->forceFill(['visibility' => 'public', 'description' => 'We drill boreholes.', 'website' => 'https://www.acmeboreholes.co.za'])->save();
        $this->acme->users()->attach($this->editor, ['role' => 'editor']);
    }

    private function report(?User $as = null, string $category = 'impersonation', array $extra = []): TestResponse
    {
        return $this->actingAs($as ?? $this->reporter)->post('/business/'.$this->acme->uuid.'/report', $extra + ['category' => $category, 'detail' => self::DETAIL]);
    }

    private function openRestricted(?User $as = null): BusinessInvestigation
    {
        $this->report()->assertRedirect();
        $report = BusinessReport::latest('id')->firstOrFail();

        $this->actingAs($as ?? $this->reviewer)->post('/moderation/trust/investigations', [
            'team_id' => $this->acme->id, 'concern' => 'potential_impersonation', 'summary' => 'Branding matches Acme Water.',
            'report_ids' => [$report->id], 'restrict' => 1, 'message' => 'Please send proof that you own the Acme name and logo.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return BusinessInvestigation::latest('id')->firstOrFail();
    }

    private function verify(): BusinessVerification
    {
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/verification', [
            'category' => 'registered_company', 'registered_name' => 'Acme Boreholes', 'registration_number' => '2019/123456/07',
            'registration_country' => 'South Africa', 'applicant_role' => 'Director', 'declaration' => '1',
            'evidence' => [['label' => 'registration', 'file' => UploadedFile::fake()->createWithContent('cor.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF")]],
        ])->assertSessionHasNoErrors();
        $v = BusinessVerification::where('team_id', $this->acme->id)->latest('id')->firstOrFail();
        $this->actingAs($this->reviewer2)->post('/moderation/verifications/'.$v->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])->assertSessionHasNoErrors();

        return $v->fresh();
    }

    public function test_a_report_reaches_reviewers_and_changes_nothing_about_the_business(): void
    {
        $this->report(null, 'impersonation', ['files' => [UploadedFile::fake()->image('proof.png', 600, 400)]])->assertRedirect()->assertSessionHasNoErrors();
        $report = BusinessReport::sole();

        $this->assertSame('high', $report->priority);
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->reviewer->id, 'kind' => UserNotice::BUSINESS_TRUST_QUEUE]);
        $this->assertDatabaseMissing('user_notices', ['user_id' => $this->owner->id]);

        // Still listed, still open, nothing said publicly.
        $this->get('/businesses?all=1')->assertSee('Acme Boreholes');
        $this->app['auth']->forgetGuards();
        $this->get('/business/'.$this->acme->uuid)->assertOk()->assertDontSee('reviewing')->assertDontSee('Under investigation');

        // One open report per person per business; members cannot report their own business.
        $this->report()->assertSessionHasErrors('detail');
        $this->report($this->editor)->assertSessionHasErrors('detail');

        // The evidence is for the reporter and outside reviewers only.
        $file = Attachment::where('attachable_type', 'business_report')->sole();
        $this->actingAs($this->reporter)->get('/attachments/'.$file->uuid)->assertOk();
        $this->actingAs($this->reviewer)->get('/attachments/'.$file->uuid)->assertOk();
        $this->actingAs($this->owner)->get('/attachments/'.$file->uuid)->assertNotFound();
        $insider = User::factory()->withPersonalTeam()->create();
        $insider->forceFill(['is_moderator' => true])->save();
        $this->acme->users()->attach($insider, ['role' => 'editor']);
        $this->actingAs($insider)->get('/attachments/'.$file->uuid)->assertNotFound();
    }

    public function test_several_people_or_a_verified_target_make_a_report_urgent(): void
    {
        $this->report(null, 'incorrect_info')->assertRedirect();
        $this->assertSame('normal', BusinessReport::latest('id')->first()->priority);

        $this->report(User::factory()->withPersonalTeam()->create(), 'incorrect_info');
        $this->report(User::factory()->withPersonalTeam()->create(), 'incorrect_info');
        $this->assertSame('urgent', BusinessReport::latest('id')->first()->priority);
    }

    public function test_reviewers_close_reports_and_reporters_learn_only_the_outcome(): void
    {
        $this->report()->assertRedirect();
        $report = BusinessReport::sole();

        $this->actingAs($this->owner)->post('/moderation/trust/reports/'.$report->id.'/dismiss', ['note' => 'Trying to dismiss my own report.'])->assertForbidden();
        $this->actingAs($this->reviewer)->post('/moderation/trust/reports/'.$report->id.'/dismiss', ['note' => 'Logo is their own; Acme Water uses a different mark.'])->assertRedirect();

        $this->assertSame(BusinessReport::DISMISSED, $report->fresh()->status);
        $notice = UserNotice::where('user_id', $this->reporter->id)->where('kind', UserNotice::BUSINESS_REPORT_DECIDED)->sole();
        $words = app(InAppNotices::class)->describe($notice)['text'];
        $this->assertStringContainsString('found no problem', $words);
        $this->assertStringNotContainsString('Logo is their own', $words);
    }

    public function test_a_restriction_takes_the_business_out_of_discovery_without_a_label(): void
    {
        $this->actingAs($this->reviewer)->post('/moderation/trust/investigations', [
            'team_id' => $this->acme->id, 'concern' => 'reported_concern', 'summary' => 'Checking.', 'restrict' => 1,
        ])->assertSessionHasErrors('message');

        $inv = $this->openRestricted();
        $this->assertTrue($inv->restricted);
        $this->assertSame(BusinessReport::INVESTIGATING, BusinessReport::sole()->status);

        $this->app['auth']->forgetGuards();
        $this->get('/businesses?all=1')->assertDontSee('Acme Boreholes');
        $this->get('/business/'.$this->acme->uuid)->assertNotFound();
        // A reviewer may still open it, to do the review.
        $this->actingAs($this->reviewer)->get('/business/'.$this->acme->uuid)->assertOk();
        $this->actingAs($this->reporter)->get('/business/'.$this->acme->uuid)->assertNotFound();
        $this->actingAs($this->reporter)->get('/business/'.$this->acme->uuid.'/message')->assertNotFound();

        // The business's own people still see it, with the reason on the standing page; nobody is named.
        $this->actingAs($this->editor)->get('/business/'.$this->acme->uuid)->assertOk()->assertSee('out of the directory while InfoDot reviews it');
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->owner->id, 'kind' => UserNotice::BUSINESS_STANDING]);
        $this->actingAs($this->owner)->get('/business/'.$this->acme->uuid.'/standing')->assertOk()
            ->assertSee('Please send proof that you own the Acme name and logo.')
            ->assertDontSee('Rita Reporter')
            ->assertDontSee('Branding matches Acme Water.');
        $this->actingAs($this->editor)->get('/business/'.$this->acme->uuid.'/standing')->assertForbidden();
    }

    public function test_confirmed_impersonation_revokes_verification_and_closes_the_reports(): void
    {
        $v = $this->verify();
        $this->assertTrue($this->acme->fresh()->isVerified());
        $inv = $this->openRestricted();
        $this->assertSame('urgent', BusinessReport::sole()->priority, 'a serious report against a verified business');

        $this->actingAs($this->reviewer)->post('/moderation/trust/investigations/'.$inv->id.'/conclude', [
            'outcome' => BusinessInvestigation::CONFIRMED_IMPERSONATION, 'text' => 'The registration number belongs to another company.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $inv->refresh();
        $this->assertSame(BusinessInvestigation::CONFIRMED_IMPERSONATION, $inv->status);
        $this->assertSame(BusinessVerification::REVOKED, $v->fresh()->status);
        $this->assertFalse($this->acme->fresh()->isVerified());
        $this->assertTrue($this->acme->fresh()->isRestricted());
        $this->assertSame(BusinessReport::ACTIONED, BusinessReport::sole()->status);
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->reporter->id, 'kind' => UserNotice::BUSINESS_REPORT_DECIDED]);
        $this->assertSame(['opened', 'report_linked', 'business_told', 'restricted', 'concluded', 'verification_revoked'], BusinessInvestigationEvent::orderBy('id')->pluck('action')->all());
        $this->assertSame([$this->reviewer->id], BusinessInvestigationEvent::distinct()->pluck('user_id')->all());
    }

    public function test_an_appeal_goes_to_a_different_reviewer_and_can_clear_the_business(): void
    {
        $inv = $this->openRestricted();
        $this->actingAs($this->reviewer)->post('/moderation/trust/investigations/'.$inv->id.'/conclude', [
            'outcome' => BusinessInvestigation::CONFIRMED_IMPERSONATION, 'text' => 'The logo is Acme Water’s registered mark.',
        ])->assertRedirect();

        $this->actingAs($this->editor)->post('/business/'.$this->acme->uuid.'/appeals', ['subject' => 'investigation:'.$inv->id, 'body' => str_repeat('We are an Acme Water franchise. ', 3)])->assertForbidden();
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/appeals', [
            'subject' => 'investigation:'.$inv->id, 'body' => 'We are an Acme Water franchise and use the mark under licence; the agreement is attached.',
            'files' => [UploadedFile::fake()->createWithContent('licence.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF")],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $appeal = BusinessAppeal::sole();
        $this->assertSame($this->reviewer->id, $appeal->original_decider);

        // One appeal per decision.
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/appeals', ['subject' => 'investigation:'.$inv->id, 'body' => 'Appealing the same decision a second time, please.'])->assertSessionHasErrors('body');

        // The reviewer who decided cannot decide the appeal; another can.
        $this->actingAs($this->reviewer)->post('/moderation/trust/appeals/'.$appeal->id.'/uphold', ['note' => 'Self-review is not allowed here.'])->assertForbidden();
        $this->actingAs($this->reviewer2)->post('/moderation/trust/appeals/'.$appeal->id.'/uphold', ['note' => 'The licence agreement covers the name and logo. Cleared.'])->assertRedirect();

        $this->assertSame(BusinessAppeal::UPHELD, $appeal->fresh()->status);
        $this->assertSame(BusinessInvestigation::CLEARED, $inv->fresh()->status);
        $this->assertFalse($this->acme->fresh()->isRestricted());
        $this->app['auth']->forgetGuards();
        $this->get('/businesses?all=1')->assertSee('Acme Boreholes');
        $this->actingAs($this->owner)->get('/business/'.$this->acme->uuid.'/standing')->assertSee('Upheld')->assertSee('The licence agreement covers the name and logo.');
    }

    public function test_an_upheld_appeal_against_a_suspension_reinstates_the_badge(): void
    {
        $v = $this->verify();
        $this->actingAs($this->reviewer2)->post('/moderation/verifications/'.$v->id.'/suspend', ['message' => 'Your website no longer resolves; send proof it is yours.', 'note' => 'Domain lapsed?'])->assertSessionHasNoErrors();
        $this->assertSame(BusinessVerification::SUSPENDED, $v->fresh()->status);

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/appeals', ['subject' => 'verification:'.$v->id, 'body' => 'The domain was renewed the same day; the registrar receipt is attached to this appeal.'])->assertRedirect()->assertSessionHasNoErrors();
        $appeal = BusinessAppeal::sole();

        $this->actingAs($this->reviewer2)->post('/moderation/trust/appeals/'.$appeal->id.'/uphold', ['note' => 'I suspended it; I should not decide this.'])->assertForbidden();
        $this->actingAs($this->reviewer)->post('/moderation/trust/appeals/'.$appeal->id.'/uphold', ['note' => 'The domain resolves again and the receipt matches.'])->assertRedirect();
        $this->assertSame(BusinessVerification::VERIFIED, $v->fresh()->status);
        $this->assertTrue($this->acme->fresh()->isVerified());
    }

    public function test_clearing_returns_the_business_and_ordinary_users_cannot_touch_the_desk(): void
    {
        $inv = $this->openRestricted();

        foreach ([$this->owner, $this->reporter] as $user) {
            $this->actingAs($user)->get('/moderation/trust')->assertForbidden();
            $this->actingAs($user)->post('/moderation/trust/investigations/'.$inv->id.'/lift')->assertForbidden();
            $this->actingAs($user)->post('/moderation/trust/investigations/'.$inv->id.'/conclude', ['outcome' => 'cleared', 'text' => 'Clearing myself of everything at once.'])->assertForbidden();
        }
        $this->assertTrue($this->acme->fresh()->isRestricted());

        $this->actingAs($this->reviewer)->post('/moderation/trust/investigations/'.$inv->id.'/conclude', ['outcome' => 'cleared', 'text' => 'Proof received: the name and logo are theirs.'])->assertRedirect();
        $this->assertFalse($this->acme->fresh()->isRestricted());
        $this->assertSame(BusinessReport::DISMISSED, BusinessReport::sole()->status);
        $this->app['auth']->forgetGuards();
        $this->get('/business/'.$this->acme->uuid)->assertOk();
    }
}
