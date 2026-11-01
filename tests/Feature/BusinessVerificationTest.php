<?php

namespace Tests\Feature;

use App\Mail\VerificationEmailCode;
use App\Models\Attachment;
use App\Models\BusinessVerification;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Models\VerificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/**
 * R-29 Phase 2 (brief §34 verification testing): applying with private evidence, the reviewer
 * desk and its rules, the official-email code, the badge and trust card, re-review after
 * identity changes, suspension, revocation, expiry, renewal and evidence retention — and that
 * nobody can change a status from outside the service.
 */
class BusinessVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $editor;

    private User $outsider;

    private User $reviewer;

    private Team $acme;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->acme = Team::forceCreate(['user_id' => $this->owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $this->owner->switchTeam($this->acme);
        $this->acme->profileOrNew()->fill(['website' => 'https://www.acmeboreholes.co.za', 'registration_number' => '2019/123456/07', 'registration_country' => 'South Africa', 'visibility' => 'members'])->save();

        $this->admin = User::factory()->withPersonalTeam()->create();
        $this->acme->users()->attach($this->admin, ['role' => 'admin']);
        $this->editor = User::factory()->withPersonalTeam()->create();
        $this->acme->users()->attach($this->editor, ['role' => 'editor']);
        $this->outsider = User::factory()->withPersonalTeam()->create();
        $this->reviewer = User::factory()->withPersonalTeam()->create();
        $this->reviewer->forceFill(['is_moderator' => true])->save();
    }

    private function pdf(string $name = 'cor14-3.pdf'): UploadedFile
    {
        $stream = 'BT /F1 12 Tf 20 700 Td (Registration) Tj ET';
        $objects = [
            "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n",
            "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n",
            "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R>>endobj\n",
            '4 0 obj<</Length '.strlen($stream).">>stream\n".$stream."\nendstream endobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        foreach ($objects as $object) {
            $pdf .= $object;
        }

        return UploadedFile::fake()->createWithContent($name, $pdf."trailer<</Root 1 0 R>>\n%%EOF");
    }

    private function url(string $suffix = ''): string
    {
        return '/business/'.$this->acme->uuid.'/verification'.$suffix;
    }

    private function apply(?User $as = null): BusinessVerification
    {
        $this->actingAs($as ?? $this->owner)->post($this->url(), [
            'category' => 'registered_company',
            'registered_name' => 'Acme Boreholes (Pty) Ltd',
            'registration_number' => '2019/123456/07',
            'registration_country' => 'South Africa',
            'website' => 'https://www.acmeboreholes.co.za',
            'applicant_role' => 'Director',
            'declaration' => '1',
            'status' => 'verified', // a forged field: must be ignored
            'evidence' => [['label' => 'registration', 'file' => $this->pdf()], ['label' => 'authority', 'file' => $this->pdf('resolution.pdf')]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return BusinessVerification::firstOrFail();
    }

    private function decide(BusinessVerification $v, string $action, array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->reviewer)->post('/moderation/verifications/'.$v->id.'/'.$action, $data);
    }

    private function approve(BusinessVerification $v): void
    {
        $this->decide($v, 'approve', ['checks' => ['registration_document', 'representative_authority']])->assertSessionHasNoErrors();
    }

    public function test_an_owner_applies_with_private_evidence_and_only_the_right_people_can_open_it(): void
    {
        $v = $this->apply();

        $this->assertSame(BusinessVerification::PENDING, $v->status);
        $this->assertNotSame('2019/123456/07', $v->getRawOriginal('registration_number'), 'registration number is encrypted at rest');
        $this->assertCount(2, $v->evidence);
        $doc = $v->evidence->first();

        $this->actingAs($this->admin)->get(route('attachments.show', $doc))->assertOk();
        $this->actingAs($this->reviewer)->get(route('attachments.show', $doc))->assertOk();
        // Hidden files answer 404, as every hidden item on InfoDot does.
        $this->actingAs($this->editor)->get(route('attachments.show', $doc))->assertNotFound();
        $this->actingAs($this->outsider)->get(route('attachments.show', $doc))->assertNotFound();

        $this->actingAs($this->editor)->get($this->url())->assertForbidden();
        $this->actingAs($this->outsider)->get('/business/'.$this->acme->uuid)->assertOk()->assertSee('Not verified')->assertDontSee('What InfoDot confirmed');
        $this->assertTrue(UserNotice::where('user_id', $this->reviewer->id)->where('kind', UserNotice::VERIFICATION_QUEUE)->exists());

        // One open application at a time.
        $this->actingAs($this->owner)->post($this->url(), ['category' => 'sole_proprietor', 'registered_name' => 'Again', 'registration_country' => 'ZA', 'applicant_role' => 'Owner', 'declaration' => '1', 'evidence' => [['label' => 'other', 'file' => $this->pdf()]]])
            ->assertSessionHasErrors('team');
    }

    public function test_status_cannot_be_changed_from_outside_the_reviewer_rules(): void
    {
        $v = $this->apply();

        $this->decide($v, 'approve', ['checks' => ['registration_document', 'representative_authority']], $this->owner)->assertForbidden();
        $this->decide($v, 'approve', ['checks' => ['registration_document', 'representative_authority']], $this->outsider)->assertForbidden();

        // A moderator who belongs to the business may read the file but not decide it.
        $this->acme->users()->attach($this->reviewer, ['role' => 'member']);
        $this->decide($v, 'approve', ['checks' => ['registration_document', 'representative_authority']])->assertForbidden();
        $this->actingAs($this->reviewer)->get('/moderation/verifications/'.$v->id)->assertOk()->assertSee('another reviewer must decide');
        $this->acme->users()->detach($this->reviewer);

        $this->assertSame(BusinessVerification::PENDING, $v->fresh()->status);
        $this->assertFalse($this->acme->fresh()->isVerified());
    }

    public function test_approval_needs_two_checks_including_documents_and_then_shows_the_plate(): void
    {
        $v = $this->apply();

        $this->decide($v, 'approve', ['checks' => ['registration_document']])->assertSessionHasErrors('checks');
        $this->decide($v, 'approve', ['checks' => ['representative_authority', 'operating_presence']])->assertSessionHasErrors('checks');
        $this->decide($v, 'approve', ['checks' => ['registration_document', 'official_email']])->assertSessionHasErrors('checks');

        $this->approve($v);
        $v->refresh();

        $this->assertSame(BusinessVerification::VERIFIED, $v->status);
        $this->assertSame($this->reviewer->id, $v->decided_by);
        $this->assertTrue($v->expires_at->between(now()->addMonths(12)->subMinute(), now()->addMonths(12)->addMinute()));
        $this->assertTrue($this->acme->fresh()->isVerified());

        $this->actingAs($this->outsider)->get('/business/'.$this->acme->uuid)->assertOk()
            ->assertSee('What InfoDot confirmed')->assertSee('Registration or founding documents reviewed by InfoDot')
            ->assertSee('does not vouch for its finances')->assertDontSee('Control of an email address');
        $this->assertSame(['submitted', 'approved'], VerificationEvent::orderBy('id')->pluck('event')->all());
        $this->assertTrue(UserNotice::where('user_id', $this->owner->id)->where('kind', UserNotice::BUSINESS_VERIFICATION)->where('ref', 'like', 'bv:'.$v->id.':verified:%')->exists());
    }

    public function test_the_official_email_is_confirmed_only_at_the_business_domain_with_the_right_code(): void
    {
        $v = $this->apply();

        $this->actingAs($this->owner)->post($this->url('/email'), ['official_email' => 'acme@gmail.com'])->assertSessionHasErrors('official_email');
        $this->actingAs($this->owner)->post($this->url('/email'), ['official_email' => 'boss@acme-other.co.za'])->assertSessionHasErrors('official_email');
        $this->actingAs($this->owner)->post($this->url('/email'), ['official_email' => 'accounts@acmeboreholes.co.za'])->assertSessionHasNoErrors();

        $code = null;
        Mail::assertSent(VerificationEmailCode::class, function (VerificationEmailCode $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('accounts@acmeboreholes.co.za');
        });

        $this->actingAs($this->owner)->post($this->url('/email/confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($this->owner)->post($this->url('/email/confirm'), ['code' => $code])->assertSessionHasNoErrors();
        $this->assertNotNull($v->fresh()->official_email_confirmed_at);

        $this->decide($v, 'approve', ['checks' => ['registration_document', 'official_email']])->assertSessionHasNoErrors();
        $this->assertSame(['registration_document', 'official_email'], $v->fresh()->checks);
    }

    public function test_a_request_for_information_goes_to_the_business_and_comes_back(): void
    {
        $v = $this->apply();

        $this->decide($v, 'info', ['message' => 'short'])->assertSessionHasErrors('message');
        $this->decide($v, 'info', ['message' => 'Please add the latest CIPC annual return.'])->assertSessionHasNoErrors();
        $this->assertSame(BusinessVerification::INFO_REQUIRED, $v->fresh()->status);
        $this->actingAs($this->owner)->get($this->url())->assertSee('Please add the latest CIPC annual return.');

        $this->actingAs($this->owner)->post($this->url('/evidence'), ['evidence' => [['label' => 'other', 'file' => $this->pdf('annual-return.pdf')]]])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->url('/resubmit'))->assertSessionHasNoErrors();

        $this->assertSame(BusinessVerification::PENDING, $v->fresh()->status);
        $this->assertCount(3, $v->fresh()->evidence);
    }

    public function test_changing_verified_identity_details_takes_the_plate_off_until_reconfirmed(): void
    {
        $v = $this->apply();
        $this->approve($v);

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/profile', ['description' => 'Still drilling.'])->assertRedirect();
        $this->assertTrue($this->acme->fresh()->isVerified(), 'a description change is not an identity change');

        $this->acme->forceFill(['name' => 'Acme Water Group'])->save();
        $this->assertSame(BusinessVerification::UNDER_REVIEW, $v->fresh()->status);
        $this->assertFalse($this->acme->fresh()->isVerified());
        $this->actingAs($this->outsider)->get('/business/'.$this->acme->uuid)->assertSee('Under review')->assertDontSee('What InfoDot confirmed');

        $this->decide($v, 'reconfirm')->assertSessionHasNoErrors();
        $this->assertTrue($this->acme->fresh()->isVerified());

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/profile', ['website' => 'https://acme-water.example'])->assertRedirect();
        $this->assertSame(BusinessVerification::UNDER_REVIEW, $v->fresh()->status);
    }

    public function test_suspension_reinstatement_and_revocation_are_reflected_and_told(): void
    {
        $v = $this->apply();
        $this->approve($v);

        $this->decide($v, 'suspend', ['message' => 'A report about the registration number is being checked.'])->assertSessionHasNoErrors();
        $this->assertFalse($this->acme->fresh()->isVerified());
        $this->actingAs($this->owner)->get($this->url())->assertSee('A report about the registration number is being checked.');
        $this->actingAs($this->owner)->post($this->url(), ['category' => 'sole_proprietor', 'registered_name' => 'X', 'registration_country' => 'ZA', 'applicant_role' => 'Owner', 'declaration' => '1', 'evidence' => [['label' => 'other', 'file' => $this->pdf()]]])
            ->assertSessionHasErrors('team');

        $this->decide($v, 'reinstate')->assertSessionHasNoErrors();
        $this->assertTrue($this->acme->fresh()->isVerified());

        $this->decide($v, 'revoke', ['message' => 'The registration documents were not genuine.'])->assertSessionHasNoErrors();
        $this->assertSame(BusinessVerification::REVOKED, $v->fresh()->status);
        $this->assertFalse($this->acme->fresh()->isVerified());
        $this->assertTrue(UserNotice::where('user_id', $this->admin->id)->where('ref', 'like', 'bv:'.$v->id.':revoked:%')->exists());
    }

    public function test_verification_lapses_after_a_year_with_a_reminder_and_a_renewal_takes_over(): void
    {
        $v = $this->apply();
        $this->approve($v);

        $this->travelTo(now()->addMonths(11)->addDays(5));
        $this->artisan('business:verifications')->assertSuccessful();
        $this->assertNotNull($v->fresh()->renewal_reminded_at);
        $this->assertTrue(UserNotice::where('user_id', $this->owner->id)->where('kind', UserNotice::BUSINESS_VERIFICATION_RENEWAL)->exists());

        // Renewal opens within the warning window; the old standing holds until the new one is approved.
        $this->actingAs($this->owner)->post($this->url(), ['category' => 'registered_company', 'registered_name' => 'Acme Boreholes (Pty) Ltd', 'registration_number' => '2019/123456/07', 'registration_country' => 'South Africa', 'applicant_role' => 'Director', 'declaration' => '1', 'evidence' => [['label' => 'registration', 'file' => $this->pdf()]]])
            ->assertSessionHasNoErrors();
        $renewal = BusinessVerification::whereKeyNot($v->id)->firstOrFail();
        $this->assertTrue($this->acme->fresh()->isVerified());

        $this->approve($renewal);
        $this->assertSame(BusinessVerification::SUPERSEDED, $v->fresh()->status);
        $this->assertSame($renewal->id, $this->acme->fresh()->standing->id);

        $this->travelTo(now()->addMonths(13));
        $this->artisan('business:verifications')->assertSuccessful();
        $this->assertSame(BusinessVerification::EXPIRED, $renewal->fresh()->status);
        $this->assertFalse($this->acme->fresh()->isVerified());
    }

    public function test_evidence_of_a_closed_application_is_deleted_after_retention(): void
    {
        $v = $this->apply();
        $this->decide($v, 'reject', ['message' => 'The document is for a different company.'])->assertSessionHasNoErrors();
        $paths = $v->evidence->pluck('path')->all();

        $this->travelTo(now()->addDays(config('verification.evidence_retention_days') + 1));
        $this->artisan('business:verifications')->assertSuccessful();

        $v->refresh();
        $this->assertNotNull($v->evidence_purged_at);
        $this->assertSame(0, Attachment::where('attachable_type', 'business_verification')->count());
        $this->assertNull($v->registration_number);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_history_is_append_only_and_the_registration_number_is_masked_for_outsiders(): void
    {
        $this->apply();

        $this->expectException(LogicException::class);
        try {
            $this->actingAs($this->outsider)->get('/business/'.$this->acme->uuid)->assertDontSee('2019/123456/07')->assertSee('•••');
            $this->actingAs($this->editor)->get('/business/'.$this->acme->uuid)->assertSee('2019/123456/07');
        } finally {
            VerificationEvent::firstOrFail()->update(['note' => 'tampered']);
        }
    }
}
