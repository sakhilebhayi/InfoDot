<?php

namespace Tests\Feature;

use App\Models\BusinessRelationship;
use App\Models\BusinessVerification;
use App\Models\IdentityConflict;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Models\VerifiedIdentity;
use App\Services\Identity\IdentityProtection;
use App\Services\Identity\ImageFingerprint;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-29 Phase 3 (brief §35 scenarios 1–7): protected identities of verified businesses, matching
 * of names, registration numbers, websites and branding, risk levels, alerts, relationship
 * claims — and that a similar name alone never accuses anyone.
 */
class IdentityProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $reviewer;

    private Team $acme;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->reviewer = User::factory()->withPersonalTeam()->create();
        $this->reviewer->forceFill(['is_moderator' => true])->save();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->acme = $this->business($this->owner, 'Acme Boreholes (Pty) Ltd', [
            'registration_number' => '2019/123456/07', 'registration_country' => 'South Africa', 'website' => 'https://www.acmeboreholes.co.za',
        ]);
        $this->brand($this->owner, $this->acme, 1);
        $this->verify($this->owner, $this->acme);
    }

    /** A real PNG with a pattern of its own, so two seeds give clearly different fingerprints. */
    private function png(int $seed, string $name = 'brand.png', int $w = 600, int $h = 600): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        mt_srand($seed);
        for ($i = 0; $i < 40; $i++) {
            $c = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $c);
        }
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function business(User $owner, string $name, array $profile = []): Team
    {
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
        $owner->switchTeam($team);
        if ($profile) {
            $team->profileOrNew()->fill($profile)->save();
        }

        return $team->fresh();
    }

    private function brand(User $as, Team $team, int $seed): void
    {
        $this->actingAs($as)->post('/business/'.$team->uuid.'/media', ['role' => 'logo', 'file' => $this->png($seed, 'logo.png', 400, 400)])->assertRedirect();
        $this->actingAs($as)->post('/business/'.$team->uuid.'/media', ['role' => 'cover', 'file' => $this->png($seed + 100, 'cover.png', 1600, 500)])->assertRedirect();
    }

    private function apply(User $as, Team $team, array $fields = []): BusinessVerification
    {
        $this->actingAs($as)->post('/business/'.$team->uuid.'/verification', array_merge([
            'category' => 'registered_company', 'registered_name' => $team->name, 'registration_number' => '2021/999999/07',
            'registration_country' => 'South Africa', 'applicant_role' => 'Director', 'declaration' => '1',
            'evidence' => [['label' => 'registration', 'file' => UploadedFile::fake()->createWithContent('cor.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF")]],
        ], $fields))->assertSessionHasNoErrors();

        return BusinessVerification::where('team_id', $team->id)->latest('id')->firstOrFail();
    }

    private function verify(User $as, Team $team, array $fields = []): BusinessVerification
    {
        $v = $this->apply($as, $team, array_merge(['registration_number' => '2019/123456/07'], $fields));
        $this->actingAs($this->reviewer)->post('/moderation/verifications/'.$v->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])->assertSessionHasNoErrors();

        return $v->fresh();
    }

    private function conflictFor(Team $subject): ?IdentityConflict
    {
        return IdentityConflict::where('subject_team_id', $subject->id)->where('protected_team_id', $this->acme->id)->latest('id')->first();
    }

    public function test_approval_protects_the_identity_with_hashed_registration_and_branding_fingerprints(): void
    {
        $identity = VerifiedIdentity::where('team_id', $this->acme->id)->where('active', true)->firstOrFail();

        $this->assertSame('acme boreholes', $identity->name_key);
        $this->assertSame('acmeboreholes.co.za', $identity->website_host);
        $this->assertSame(64, strlen($identity->registration_hash));
        $this->assertStringNotContainsString('123456', json_encode($identity->toArray()));
        $this->assertSame(16, strlen((string) $identity->logo_hash));
        $this->assertSame(16, strlen((string) $identity->cover_hash));
    }

    public function test_scenario_1_exact_duplicate_is_critical_blocks_verification_and_alerts(): void
    {
        $other = User::factory()->withPersonalTeam()->create();
        $copy = $this->business($other, 'ACME BOREHOLES PTY LTD', ['registration_number' => '2019 123456 07', 'registration_country' => 'South Africa']);

        $conflict = $this->conflictFor($copy);
        $this->assertNotNull($conflict);
        $this->assertSame('critical', $conflict->level);
        $this->assertEqualsCanonicalizing(['registration_number', 'name_exact', 'same_country'], array_column($conflict->signals, 'type'));

        $v = $this->apply($other, $copy, ['registration_number' => '2019/123456/07']);
        $this->actingAs($this->reviewer)->post('/moderation/verifications/'.$v->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])
            ->assertSessionHasErrors('checks');
        $this->assertFalse($copy->fresh()->isVerified());

        $this->assertTrue(UserNotice::where('user_id', $this->reviewer->id)->where('kind', UserNotice::IDENTITY_CONFLICT)->exists());
        $this->assertTrue(UserNotice::where('user_id', $this->owner->id)->where('kind', UserNotice::IDENTITY_ALERT)->exists());
        $this->assertFalse(UserNotice::where('user_id', $other->id)->whereIn('kind', [UserNotice::IDENTITY_CONFLICT, UserNotice::IDENTITY_ALERT])->exists(), 'the other account is not told it is suspected');
        $this->actingAs($other)->get('/business/'.$copy->uuid.'/verification')->assertSee('An extra check applies')->assertDontSee('impersonat');
    }

    public function test_scenario_2_copied_branding_is_detected_and_weighed_with_the_rest(): void
    {
        $other = User::factory()->withPersonalTeam()->create();
        $copycat = $this->business($other, 'Waterline Drilling');
        $this->brand($other, $copycat, 1); // the same images Acme uses

        $conflict = $this->conflictFor($copycat);
        $this->assertNotNull($conflict);
        $this->assertSame('medium', $conflict->level);
        $this->assertEqualsCanonicalizing(['logo', 'cover'], array_column($conflict->signals, 'type'));

        // Different branding does not match.
        $clean = $this->business(User::factory()->withPersonalTeam()->create(), 'Fresh Water Pumps');
        $this->brand(User::find($clean->user_id), $clean, 7);
        $this->assertNull($this->conflictFor($clean));
        $this->assertGreaterThan(8, ImageFingerprint::distance(
            (string) $clean->currentMedia('logo')->meta['dhash'],
            (string) VerifiedIdentity::where('team_id', $this->acme->id)->value('logo_hash'),
        ));
    }

    public function test_scenario_3_minor_name_variation_is_recorded_as_low_without_alerts_or_blocking(): void
    {
        $other = User::factory()->withPersonalTeam()->create();
        $lookalike = $this->business($other, 'Acrne B0reholes');
        $variant = $this->business(User::factory()->withPersonalTeam()->create(), 'Acme Borehles');

        $this->assertSame('name_lookalike', $this->conflictFor($lookalike)->signals[0]['type']);
        $this->assertSame('name_similar', $this->conflictFor($variant)->signals[0]['type']);
        $this->assertSame('low', $this->conflictFor($lookalike)->level);
        $this->assertSame('low', $this->conflictFor($variant)->level);
        $this->assertFalse(UserNotice::where('kind', UserNotice::IDENTITY_CONFLICT)->exists());

        // Low does not withhold verification.
        $v = $this->apply($other, $lookalike);
        $this->actingAs($this->reviewer)->post('/moderation/verifications/'.$v->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])->assertSessionHasNoErrors();
    }

    public function test_scenario_4_a_branch_by_the_same_owner_is_expected_and_a_confirmed_branch_clears_the_conflict(): void
    {
        $branch = $this->business($this->owner, 'Acme Boreholes Rustenburg');
        $this->brand($this->owner, $branch, 1);
        $this->assertNull($this->conflictFor($branch), 'the verified business’s own owner is not flagged');

        $manager = User::factory()->withPersonalTeam()->create();
        $franchise = $this->business($manager, 'Acme Boreholes', ['registration_country' => 'South Africa']);
        $this->brand($manager, $franchise, 1);
        $this->assertSame('high', $this->conflictFor($franchise)->level); // name 30 + logo 25 + cover 15 + country 5

        $this->actingAs($manager)->post('/business/'.$franchise->uuid.'/relationships', ['code' => $this->acme->code, 'kind' => 'franchise', 'note' => 'Polokwane franchise'])->assertSessionHasNoErrors();
        $link = BusinessRelationship::firstOrFail();
        $this->assertTrue(UserNotice::where('user_id', $this->owner->id)->where('kind', UserNotice::RELATIONSHIP_REQUEST)->exists());

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/relationships/'.$link->id.'/confirm')->assertSessionHasNoErrors();
        $this->assertSame('related', $this->conflictFor($franchise)->status);

        $v = $this->apply($manager, $franchise);
        $this->actingAs($this->reviewer)->post('/moderation/verifications/'.$v->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])->assertSessionHasNoErrors();
        $this->assertTrue($franchise->fresh()->isVerified());
    }

    public function test_scenario_5_an_employee_account_is_confirmed_only_by_the_owner_or_an_administrator(): void
    {
        $member = User::factory()->withPersonalTeam()->create();
        $this->acme->users()->attach($member, ['role' => 'member']);

        $employee = User::factory()->withPersonalTeam()->create();
        $staff = $this->business($employee, 'Thandi Mokoena Sales');
        $this->actingAs($employee)->post('/business/'.$staff->uuid.'/relationships', ['code' => $this->acme->code, 'kind' => 'representative'])->assertSessionHasNoErrors();
        $link = BusinessRelationship::firstOrFail();

        $this->actingAs($member)->post('/business/'.$this->acme->uuid.'/relationships/'.$link->id.'/confirm')->assertForbidden();
        $this->actingAs($employee)->post('/business/'.$this->acme->uuid.'/relationships/'.$link->id.'/confirm')->assertForbidden();
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/relationships/'.$link->id.'/confirm')->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $link->fresh()->status);
        $this->assertTrue(UserNotice::where('user_id', $employee->id)->where('kind', UserNotice::RELATIONSHIP_DECIDED)->exists());

        // Only a verified business can be asked to confirm.
        $unverified = $this->business(User::factory()->withPersonalTeam()->create(), 'Nobody Verified');
        $this->actingAs($employee)->post('/business/'.$staff->uuid.'/relationships', ['code' => $unverified->code, 'kind' => 'branch'])->assertSessionHasErrors('code');
    }

    public function test_scenario_6_a_different_business_with_a_similar_name_is_never_accused(): void
    {
        $other = User::factory()->withPersonalTeam()->create();
        $neighbour = $this->business($other, 'Acme Borehole Supplies', ['registration_number' => 'NA-2020-5531', 'registration_country' => 'Namibia', 'website' => 'https://acme-supplies.com.na']);
        $this->brand($other, $neighbour, 42);

        $conflict = $this->conflictFor($neighbour);
        $this->assertTrue($conflict === null || $conflict->level === 'low');
        $this->assertFalse(UserNotice::whereIn('kind', [UserNotice::IDENTITY_CONFLICT, UserNotice::IDENTITY_ALERT])->exists());
        $this->actingAs($other)->get('/business/'.$neighbour->uuid.'/verification')->assertDontSee('An extra check applies');
    }

    public function test_scenario_7_registration_number_reuse_withholds_verification_until_resolved(): void
    {
        $other = User::factory()->withPersonalTeam()->create();
        $reuse = $this->business($other, 'Blue Crane Water', ['registration_number' => '2019/123456/07', 'registration_country' => 'South Africa']);

        $conflict = $this->conflictFor($reuse);
        $this->assertSame('high', $conflict->level);
        $this->assertSame(['registration_number', 'same_country'], array_column($conflict->signals, 'type'));

        $v = $this->apply($other, $reuse);
        $this->actingAs($this->reviewer)->post('/moderation/verifications/'.$v->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])->assertSessionHasErrors('checks');

        // A reviewer in either business cannot decide; an independent one can.
        $this->acme->users()->attach($this->reviewer, ['role' => 'member']);
        $this->actingAs($this->reviewer->fresh())->post('/moderation/identity/'.$conflict->id.'/dismiss', ['note' => 'Typo in the registration number, corrected.'])->assertForbidden();
        $this->acme->users()->detach($this->reviewer);
        $this->reviewer = $this->reviewer->fresh();
        $this->actingAs($this->reviewer)->get('/moderation/identity/'.$conflict->id)->assertOk()->assertSee('Same registration number as the verified business')->assertDontSee('123456');
        $this->actingAs($this->reviewer)->post('/moderation/identity/'.$conflict->id.'/dismiss', ['note' => 'Typo in the registration number, corrected.'])->assertSessionHasNoErrors();

        // The same evidence does not reopen it; approval is no longer withheld.
        $reuse->profile->forceFill(['website' => 'https://bluecrane.example'])->save();
        $this->assertSame('dismissed', $this->conflictFor($reuse)->status);
        $this->actingAs($this->reviewer)->post('/moderation/verifications/'.$v->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])->assertSessionHasNoErrors();
    }

    public function test_revocation_ends_protection_and_escalation_keeps_verification_withheld(): void
    {
        $other = User::factory()->withPersonalTeam()->create();
        $copy = $this->business($other, 'Acme Boreholes', ['registration_number' => '2019/123456/07', 'registration_country' => 'South Africa']);
        $conflict = $this->conflictFor($copy);
        $this->actingAs($this->reviewer)->post('/moderation/identity/'.$conflict->id.'/escalate', ['note' => 'Same registration number and name; documents differ.'])->assertSessionHasNoErrors();
        $this->assertSame('escalated', $conflict->fresh()->status);
        $this->assertNotNull(app(IdentityProtection::class)->blockingConflict($copy));

        $standing = $this->acme->fresh()->standing;
        app(VerificationService::class)->revoke($this->reviewer, $standing, 'Documents were not genuine after investigation.');
        $this->assertFalse(VerifiedIdentity::where('team_id', $this->acme->id)->where('active', true)->exists());

        $later = $this->business(User::factory()->withPersonalTeam()->create(), 'Acme Boreholes', ['registration_number' => '2019/123456/07', 'registration_country' => 'South Africa']);
        $this->assertNull($this->conflictFor($later));
    }

    public function test_the_daily_sweep_backfills_identities_for_businesses_verified_before_protection(): void
    {
        VerifiedIdentity::query()->delete();

        $this->artisan('business:verifications')->expectsOutputToContain('1 identity record(s) created')->assertSuccessful();
        $this->assertTrue(VerifiedIdentity::where('team_id', $this->acme->id)->where('active', true)->exists());
        $this->artisan('business:verifications')->expectsOutputToContain('0 identity record(s) created')->assertSuccessful();
    }
}
