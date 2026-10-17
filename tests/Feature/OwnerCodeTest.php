<?php

namespace Tests\Feature;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use App\Support\OwnerCode;
use App\Support\Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every company has a four-letter owner code, and every question or solution has a
 * reference built from it (NWFN-Q-000014). References are searchable but can never
 * open something the person may not see.
 */
class OwnerCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_codes_are_four_capital_letters_derived_from_the_name(): void
    {
        $none = fn () => false;

        $this->assertSame('NOFI', OwnerCode::for('Northwind Finance', $none));
        $this->assertSame('ACME', OwnerCode::for('Acme Ltd', $none));
        $this->assertSame('SAKH', OwnerCode::for("Sakhile's Team", $none));
        $this->assertSame('GLOB', OwnerCode::for('Globex Corp', $none));
        $this->assertSame('INFO', OwnerCode::for('', $none));
        $this->assertSame('ABXX', OwnerCode::for('AB', $none));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}$/', OwnerCode::for('Zürich & Söhne (Pty) Ltd', $none));
    }

    public function test_a_taken_code_keeps_the_name_readable_and_stays_unique(): void
    {
        $taken = ['ACHO'];
        $second = OwnerCode::for('Acme Holdings', fn ($c) => in_array($c, $taken, true));

        $this->assertNotSame('ACHO', $second);
        $this->assertStringStartsWith('ACH', $second);

        $everything = fn ($c) => ! str_starts_with($c, 'Q') ? true : false;
        $this->assertStringStartsWith('Q', OwnerCode::for('Q', $everything));
    }

    public function test_every_new_team_gets_a_unique_code_that_survives_a_rename(): void
    {
        $owner = User::factory()->create();
        $a = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Acme Ltd', 'personal_team' => false]);
        $b = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Acme', 'personal_team' => false]);

        $this->assertSame('ACME', $a->code);
        $this->assertNotSame($a->code, $b->code);
        $this->assertStringStartsWith('ACM', $b->code);

        $a->forceFill(['name' => 'Totally Different'])->save();
        $this->assertSame('ACME', $a->fresh()->code);
    }

    public function test_the_migration_gives_existing_teams_codes(): void
    {
        $owner = User::factory()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Legacy Co', 'personal_team' => false]);
        DB::table('teams')->where('id', $team->id)->update(['code' => null]);

        (require database_path('migrations/2026_10_17_190000_add_code_to_teams_table.php'))->up();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}$/', DB::table('teams')->where('id', $team->id)->value('code'));
    }

    private function member(string $teamName): array
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::forceCreate(['user_id' => $user->id, 'name' => $teamName, 'personal_team' => false]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user, $team];
    }

    private function question(User $by, Team $team, string $title, string $visibility = 'company'): Questions
    {
        return Questions::withoutSyncingToSearch(fn () => Questions::create([
            'user_id' => $by->id, 'team_id' => $team->id, 'visibility' => $visibility,
            'question' => $title, 'description' => 'd', 'status' => 0,
        ]));
    }

    public function test_a_reference_names_the_owner_the_kind_and_the_serial(): void
    {
        [$ann, $acme] = $this->member('Acme Ltd');
        $q = $this->question($ann, $acme, 'Who approves?');
        $s = Solutions::withoutSyncingToSearch(fn () => Solutions::create([
            'user_id' => $ann->id, 'team_id' => $acme->id, 'visibility' => 'company', 'solution_title' => 'S', 'solution_description' => 'd',
            'duration' => 1, 'duration_type' => 'hours', 'steps' => 1,
        ]));

        $this->assertSame(sprintf('ACME-Q-%06d', $q->id), Reference::for($q));
        $this->assertSame(sprintf('ACME-S-%06d', $s->id), Reference::for($s));
    }

    public function test_resolving_a_reference_obeys_visibility_and_the_owner_code(): void
    {
        [$ann, $acme] = $this->member('Acme Ltd');
        [$gina, $globex] = $this->member('Globex Corp');
        $mine = $this->question($ann, $acme, 'Acme private');
        $theirs = $this->question($gina, $globex, 'Globex private');
        $theirsPublic = $this->question($gina, $globex, 'Globex public', 'public');

        $this->actingAs($ann);

        $this->assertSame($mine->id, Reference::resolve(Reference::for($mine))?->id);
        $this->assertSame($mine->id, Reference::resolve(' acme-q-'.$mine->id.' ')?->id, 'case, spacing and short serials are tolerated');
        $this->assertSame($theirsPublic->id, Reference::resolve(Reference::for($theirsPublic))?->id);
        $this->assertNull(Reference::resolve(Reference::for($theirs)), 'another company private item does not resolve');
        $this->assertNull(Reference::resolve('ACME-Q-'.sprintf('%06d', $theirsPublic->id)), 'a forged owner code does not resolve');
        $this->assertNull(Reference::resolve('not a reference'));
        $this->assertNull(Reference::resolve('ACME-Z-000001'));
    }
}
