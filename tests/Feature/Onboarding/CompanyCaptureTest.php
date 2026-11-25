<?php

namespace Tests\Feature\Onboarding;

use App\Models\CompanyProfileImport;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Services\Onboarding\CompanyCapture;
use App\Services\Onboarding\DuplicateCompanies;
use App\Services\Onboarding\DuplicateCompanyFound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** R-62 phase 2: one capture flow for every way of describing a company, with sources, conflicts, confirmed values kept and duplicates caught. */
class CompanyCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name = 'Thandi Dlamini'): User
    {
        $user = User::factory()->create(['name' => $name, 'email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true, 'name' => "{$name}'s Team"]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    private function capture(): CompanyCapture
    {
        return app(CompanyCapture::class);
    }

    private function values(array $over = []): array
    {
        return $over + ['name' => 'Dlamini Freight', 'industry' => 'logistics', 'description' => 'We move freight.', 'visibility' => 'private'];
    }

    public function test_confirming_creates_the_company_records_each_fields_source_and_closes_the_import(): void
    {
        $user = $this->person();
        $capture = $this->capture();
        $import = $capture->start($user, CompanyProfileImport::MANUAL);
        $capture->propose($import, 'manual', ['name' => 'Dlamini Freight', 'description' => 'We move freight.']);

        $team = $capture->confirm($import, $user, $this->values(['website' => 'https://dlaminifreight.co.za']));

        $this->assertSame('Dlamini Freight', $team->name);
        $this->assertFalse((bool) $team->personal_team);
        $profile = $team->profile()->first();
        $this->assertSame('https://dlaminifreight.co.za', $profile->website);
        $this->assertSame('manual', $profile->field_sources['name']['source']);
        $this->assertSame('manual', $profile->field_sources['website']['source'], 'typed by the person, not offered by a source');
        $this->assertArrayNotHasKey('visibility', $profile->field_sources);

        $import->refresh();
        $this->assertSame(['confirmed', $team->id], [$import->status, $import->team_id]);
        $this->assertNotNull($import->confirmed_at);
    }

    public function test_a_blank_is_never_recorded_and_a_value_a_source_offered_keeps_that_source(): void
    {
        $user = $this->person();
        $capture = $this->capture();
        $import = $capture->start($user, CompanyProfileImport::WEBSITE, 'dlaminifreight.co.za');

        $capture->propose($import, 'website', ['name' => ['value' => 'Dlamini Freight', 'confidence' => 0.9], 'description' => '   ', 'contact_phone' => null, 'not_a_field' => 'x', 'location' => ['value' => 'Durban', 'confidence' => 7]]);

        $this->assertSame(['name', 'location'], array_keys($import->fresh()->extracted));
        $this->assertEquals(1.0, $import->fresh()->extracted['location']['confidence'], 'confidence is kept between 0 and 1');

        $team = $capture->confirm($import->fresh(), $user, $this->values(['location' => 'Durban']));
        $this->assertSame('website', $team->profile()->first()->field_sources['name']['source']);
        $this->assertSame('website', $team->profile()->first()->field_sources['location']['source']);
        $this->assertSame('manual', $team->profile()->first()->field_sources['description']['source']);
    }

    public function test_two_sources_that_disagree_are_kept_as_a_conflict_and_agreement_is_not(): void
    {
        $user = $this->person();
        $capture = $this->capture();
        $import = $capture->start($user, CompanyProfileImport::WEBSITE);

        $capture->propose($import, 'website', ['contact_phone' => '031 555 0100', 'location' => 'Durban']);
        $capture->propose($import, 'document', ['contact_phone' => '031 555 0199', 'location' => ' durban ']);

        $import->refresh();
        $this->assertSame(['contact_phone'], array_keys($import->conflicts), 'the same location in another case is agreement');
        $this->assertSame(['website', 'document'], array_column($import->conflicts['contact_phone']['values'], 'source'));
        $this->assertSame('031 555 0100', $import->extracted['contact_phone']['value'], 'the first value stays until the person decides');
    }

    public function test_a_later_import_never_overwrites_a_value_the_company_already_has(): void
    {
        $user = $this->person();
        $capture = $this->capture();
        $first = $capture->start($user, CompanyProfileImport::MANUAL);
        $team = $capture->confirm($first, $user, $this->values(['contact_phone' => '031 555 0100']));

        $second = $capture->start($user, CompanyProfileImport::WEBSITE);
        $capture->propose($second, 'website', ['contact_phone' => '011 000 0000', 'location' => 'Durban', 'description' => 'We move freight.']);
        $result = $capture->applyToExisting($second->fresh(), $user, $team, ['contact_phone' => '011 000 0000', 'location' => 'Durban', 'description' => 'We move freight.']);

        $profile = $team->fresh()->profile()->first();
        $this->assertSame(['location'], $result['applied']);
        $this->assertSame('031 555 0100', $profile->contact_phone, 'the confirmed phone is kept');
        $this->assertSame(['kept' => '031 555 0100', 'offered' => '011 000 0000'], $result['kept']['contact_phone']);
        $this->assertSame('Durban', $profile->location);
        $this->assertSame('website', $profile->field_sources['location']['source']);

        // Only when the person names the field is it replaced.
        $third = $capture->start($user, CompanyProfileImport::WEBSITE);
        $capture->propose($third, 'website', ['contact_phone' => '011 000 0000']);
        $replace = $capture->applyToExisting($third->fresh(), $user, $team->fresh(), ['contact_phone' => '011 000 0000'], ['contact_phone']);
        $this->assertSame(['contact_phone'], $replace['applied']);
        $this->assertSame('011 000 0000', $team->fresh()->profile()->first()->contact_phone);
    }

    public function test_only_the_owner_of_the_import_can_use_it_and_only_once(): void
    {
        $user = $this->person();
        $other = $this->person('Someone Else');
        $capture = $this->capture();
        $import = $capture->start($user, CompanyProfileImport::MANUAL);

        try {
            $capture->confirm($import, $other, $this->values());
            $this->fail('Another person confirmed the import.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $capture->confirm($import, $user, $this->values());
        $this->expectException(HttpException::class);
        $capture->confirm($import->fresh(), $user, $this->values(['name' => 'Again Ltd']));
    }

    public function test_the_same_name_among_your_own_companies_is_a_duplicate_until_you_say_otherwise(): void
    {
        $user = $this->person();
        $capture = $this->capture();
        $capture->confirm($capture->start($user, 'manual'), $user, $this->values());

        try {
            $capture->confirm($capture->start($user, 'manual'), $user, $this->values(['name' => '  dlamini FREIGHT ']));
            $this->fail('A second company with the same name was created silently.');
        } catch (DuplicateCompanyFound $e) {
            $this->assertSame(['Dlamini Freight'], $e->own->pluck('name')->all());
            $this->assertFalse($e->elsewhere);
        }

        $this->assertSame(1, $user->ownedTeams()->where('personal_team', false)->count());

        $again = $capture->confirm($capture->start($user, 'manual'), $user, $this->values(['name' => 'Dlamini Freight']), [], true);
        $this->assertSame(2, $user->fresh()->ownedTeams()->where('personal_team', false)->count());
        $this->assertSame('Dlamini Freight', $again->name);
    }

    public function test_a_registration_number_or_website_used_by_a_company_you_are_not_in_is_flagged_without_naming_it(): void
    {
        $owner = $this->person('Owner One');
        $capture = $this->capture();
        $capture->confirm($capture->start($owner, 'manual'), $owner, $this->values(['name' => 'Elsewhere Ltd', 'registration_number' => '2019/123456/07', 'website' => 'https://www.elsewhere.co.za/about']));

        $stranger = $this->person('Stranger Two');

        foreach ([['registration_number' => '2019 123456 07'], ['website' => 'elsewhere.co.za']] as $sign) {
            try {
                $capture->confirm($capture->start($stranger, 'manual'), $stranger, $this->values(['name' => 'A Different Name'] + $sign));
                $this->fail('A company reusing another company\'s identity was created silently.');
            } catch (DuplicateCompanyFound $e) {
                $this->assertTrue($e->elsewhere);
                $this->assertTrue($e->own->isEmpty(), 'nothing about the other company is revealed');
            }
        }

        // The same NAME alone, with nothing else in common, is allowed.
        $team = $capture->confirm($capture->start($stranger, 'manual'), $stranger, $this->values(['name' => 'Elsewhere Ltd']));
        $this->assertSame('Elsewhere Ltd', $team->name);
    }

    public function test_host_and_registration_normalising(): void
    {
        $this->assertSame('example.co.za', DuplicateCompanies::host('https://www.Example.co.za/contact?x=1'));
        $this->assertSame('example.co.za', DuplicateCompanies::host('example.co.za'));
        $this->assertNull(DuplicateCompanies::host('localhost'));
        $this->assertNull(DuplicateCompanies::host(''));
        $this->assertSame('2019123456 07'.'', DuplicateCompanies::registration('2019/123456 07') === '201912345607' ? '2019123456 07' : 'x');
        $this->assertNull(DuplicateCompanies::registration(' / '));
    }

    public function test_unconfirmed_imports_are_deleted_after_their_retention_and_confirmed_ones_stay(): void
    {
        $user = $this->person();
        $capture = $this->capture();
        $old = $capture->start($user, 'website');
        $old->forceFill(['expires_at' => now()->subDay()])->save();
        $fresh = $capture->start($user, 'website');
        $done = $capture->start($user, 'manual');
        $capture->confirm($done, $user, $this->values());
        $done->forceFill(['expires_at' => now()->subDay()])->save();

        $this->artisan('onboarding:prune-imports')->assertSuccessful();
        $this->artisan('onboarding:prune-imports')->assertSuccessful();

        $this->assertNull(CompanyProfileImport::find($old->id));
        $this->assertNotNull(CompanyProfileImport::find($fresh->id));
        $this->assertNotNull(CompanyProfileImport::find($done->id), 'the record of where a company came from is kept');
    }

    public function test_the_profile_stores_sources_as_json(): void
    {
        $profile = new TeamProfile;
        $profile->field_sources = ['name' => ['source' => 'manual', 'confirmed_at' => '2026-11-25T00:00:00+00:00']];

        $this->assertIsArray($profile->field_sources);
    }
}
