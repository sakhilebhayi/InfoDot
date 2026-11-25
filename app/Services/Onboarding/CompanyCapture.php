<?php

namespace App\Services\Onboarding;

use App\Models\CompanyProfileImport;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Laravel\Jetstream\Contracts\CreatesTeams;

/**
 * Describing a company, whichever way the details arrive (R-62 phase 2): typed by hand now, read from a website or a document in the later phases.
 * Every way goes through the same steps: start an import, let each source propose values (a blank is never recorded, so nothing is invented),
 * note where two sources disagree, let the person confirm, then create the company or update the one that exists. Confirmed values are protected:
 * a later import never overwrites a value the company already has unless the person asks for that field to be replaced.
 */
class CompanyCapture
{
    /** The company details this flow captures. `name` and `industry` live on the company, the rest on its profile. */
    public const FIELDS = ['name', 'industry', 'description', 'website', 'contact_email', 'contact_phone', 'location', 'registration_number', 'links'];

    private const PROFILE_ONLY = ['website', 'contact_email', 'contact_phone', 'location', 'registration_number', 'links'];

    public function __construct(private readonly DuplicateCompanies $duplicates) {}

    public function start(User $user, string $source, ?string $reference = null): CompanyProfileImport
    {
        if (! in_array($source, CompanyProfileImport::SOURCES, true)) {
            throw new \InvalidArgumentException('Unknown source.');
        }

        return CompanyProfileImport::create([
            'user_id' => $user->id,
            'source' => $source,
            'status' => CompanyProfileImport::DRAFT,
            'source_ref' => $reference === null ? null : mb_substr($reference, 0, 255),
            'expires_at' => now()->addDays((int) config('onboarding.import_retention_days', 30)),
        ]);
    }

    /**
     * What one source offers: field => value, or field => ['value' => ..., 'confidence' => 0..1]. A blank is skipped. If a different source already
     * offered a different value for the field, both are kept as a conflict for the person to settle, and the first stays in place meanwhile.
     *
     * @param  array<string, mixed>  $fields
     */
    public function propose(CompanyProfileImport $import, string $source, array $fields): CompanyProfileImport
    {
        $extracted = $import->extracted ?? [];
        $conflicts = $import->conflicts ?? [];

        foreach ($fields as $field => $item) {
            if (! in_array($field, self::FIELDS, true)) {
                continue;
            }

            $value = is_array($item) && array_key_exists('value', $item) ? $item['value'] : $item;
            if (self::blank($value)) {
                continue;
            }

            $entry = ['value' => $value, 'confidence' => max(0.0, min(1.0, (float) (is_array($item) ? ($item['confidence'] ?? 1.0) : 1.0))), 'source' => $source];
            $existing = $extracted[$field] ?? null;

            if ($existing === null) {
                $extracted[$field] = $entry;
            } elseif ($existing['source'] !== $source && self::same($existing['value'], $value)) {
                continue; // two sources agree: nothing to settle
            } elseif ($existing['source'] !== $source) {
                $conflicts[$field] = ['values' => array_merge($conflicts[$field]['values'] ?? [$existing], [$entry])];
            } else {
                $extracted[$field] = $entry; // the same source speaking again replaces its own earlier answer
            }
        }

        $import->forceFill(['extracted' => $extracted, 'conflicts' => $conflicts === [] ? null : $conflicts])->save();

        return $import;
    }

    /**
     * Creates the company from what the person confirmed. Goes through the ordinary team action (same validation, profile row and audit line).
     *
     * @param  array<string, mixed>  $values  the confirmed company details, plus `visibility`
     * @param  array<string, mixed>  $profileExtra  further profile columns to set (stage, size band, aims)
     *
     * @throws DuplicateCompanyFound when it looks like a company that exists and the person has not said to go ahead
     */
    public function confirm(CompanyProfileImport $import, User $user, array $values, array $profileExtra = [], bool $allowDuplicate = false): Team
    {
        $this->assertOpen($import, $user);

        $found = $this->duplicates->find($user, $values);
        if (! $allowDuplicate && ($found['own']->isNotEmpty() || $found['elsewhere'])) {
            throw new DuplicateCompanyFound($found['own'], $found['elsewhere']);
        }

        $created = app(CreatesTeams::class)->create($user, Arr::only($values, ['name', 'industry', 'description', 'visibility']));
        $team = Team::query()->findOrFail($created->getKey());

        $profile = $team->profileOrNew();
        $profile->fill(array_merge(array_filter(Arr::only($values, self::PROFILE_ONLY), fn ($v) => ! self::blank($v)), $profileExtra));
        $profile->fill(['field_sources' => $this->sourcesFor($import, $values)]);
        $profile->save();

        $import->forceFill([
            'team_id' => $team->id,
            'status' => CompanyProfileImport::CONFIRMED,
            'confirmed' => Arr::only($values, self::FIELDS),
            'confirmed_at' => now(),
            'conflicts' => null,
        ])->save();

        return $team;
    }

    /**
     * Brings details into a company that already exists. A field the company already has a value for is KEPT unless the person names it in
     * `$overwrite`; a different offered value is reported back so it can be shown, never applied silently.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $overwrite
     * @return array{applied: list<string>, kept: array<string, array{kept: mixed, offered: mixed}>}
     */
    public function applyToExisting(CompanyProfileImport $import, User $user, Team $team, array $values, array $overwrite = []): array
    {
        $this->assertOpen($import, $user);
        abort_unless($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin'), 403);

        $profile = $team->profileOrNew();
        $sources = $profile->field_sources ?? [];
        $applied = [];
        $kept = [];

        foreach (self::FIELDS as $field) {
            $offered = $values[$field] ?? null;
            if (self::blank($offered)) {
                continue;
            }

            $current = $this->currentValue($team, $profile, $field);
            if (! self::blank($current) && self::same($current, $offered)) {
                continue;
            }
            if (! self::blank($current) && ! in_array($field, $overwrite, true)) {
                $kept[$field] = ['kept' => $current, 'offered' => $offered];

                continue;
            }

            $this->write($team, $profile, $field, $offered);
            $sources[$field] = ['source' => $this->sourceOf($import, $field, $offered), 'confirmed_at' => now()->toIso8601String()];
            $applied[] = $field;
        }

        $profile->fill(['field_sources' => $sources]);
        $profile->save();
        $team->save();

        $import->forceFill(['team_id' => $team->id, 'status' => CompanyProfileImport::CONFIRMED, 'confirmed' => Arr::only($values, $applied), 'confirmed_at' => now(), 'conflicts' => $kept === [] ? null : $kept])->save();

        return ['applied' => $applied, 'kept' => $kept];
    }

    /** @param  array<string, mixed>  $values */
    private function sourcesFor(CompanyProfileImport $import, array $values): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            if (! self::blank($values[$field] ?? null)) {
                $out[$field] = ['source' => $this->sourceOf($import, $field, $values[$field]), 'confirmed_at' => now()->toIso8601String()];
            }
        }

        return $out;
    }

    /** Where a confirmed value came from: the source that offered exactly it, else the person (they typed or edited it). */
    private function sourceOf(CompanyProfileImport $import, string $field, mixed $value): string
    {
        $offered = $import->extracted[$field] ?? null;

        return $offered !== null && self::same($offered['value'], $value) ? $offered['source'] : CompanyProfileImport::MANUAL;
    }

    private function currentValue(Team $team, TeamProfile $profile, string $field): mixed
    {
        return in_array($field, ['name', 'industry'], true) ? $team->{$field} : $profile->{$field};
    }

    private function write(Team $team, TeamProfile $profile, string $field, mixed $value): void
    {
        if (in_array($field, ['name', 'industry'], true)) {
            $team->forceFill([$field => $value]);

            return;
        }

        $profile->forceFill([$field => $value]);
    }

    private function assertOpen(CompanyProfileImport $import, User $user): void
    {
        abort_unless($import->user_id === $user->id, 403);
        abort_unless($import->status === CompanyProfileImport::DRAFT, 409, 'That import is already finished.');
    }

    public static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '');
    }

    private static function same(mixed $a, mixed $b): bool
    {
        $norm = fn ($v) => is_array($v) ? json_encode($v) : mb_strtolower(trim((string) $v));

        return $norm($a) === $norm($b);
    }
}
