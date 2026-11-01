<?php

namespace App\Services\Identity;

use App\Models\BusinessRelationship;
use App\Models\BusinessVerification;
use App\Models\IdentityConflict;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Models\VerifiedIdentity;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Protects verified business identities and finds accounts that match them (R-29 Part C).
 *
 *  - protect(): on approval, a verified business's names, registration number (hashed), web
 *    domains and branding fingerprints become a protected identity;
 *  - evaluate(): whenever an account is created, renamed, edits its profile or branding, or
 *    applies for verification, it is compared with every protected identity. Signals add up to a
 *    score and a level (config/identity.php). Low is only recorded; medium and above withhold
 *    verification and tell reviewers; high and above also tell the verified business;
 *  - relationships: an account can claim to be a branch, subsidiary, franchise, distributor or
 *    representative of a verified business, which confirms or rejects it. Accounts the verified
 *    business's own owner runs, and confirmed relationships, are never conflicts (§19).
 *
 * Nothing here calls anyone an impersonator. A conflict is a reason for a person to look.
 */
class IdentityProtection
{
    public function __construct(private readonly InAppNotices $notices) {}

    // ------------------------------------------------------------------------------------------ protection

    public function protect(BusinessVerification $v): VerifiedIdentity
    {
        $team = $v->team()->firstOrFail();
        $profile = $team->profile()->first();
        $logo = $team->currentMedia('logo');
        $cover = $team->currentMedia('cover');
        $name = IdentityNormalizer::name($v->registered_name) ?? IdentityNormalizer::name($team->name);
        $trading = IdentityNormalizer::name($v->trading_name ?: $profile?->trading_name);

        return DB::transaction(function () use ($team, $v, $profile, $logo, $cover, $name, $trading) {
            VerifiedIdentity::where('team_id', $team->id)->where('active', true)->update(['active' => false]);

            return VerifiedIdentity::create([
                'team_id' => $team->id,
                'business_verification_id' => $v->id,
                'active' => true,
                'name_key' => $name,
                'name_skeleton' => IdentityNormalizer::skeleton($name),
                'trading_key' => $trading,
                'trading_skeleton' => IdentityNormalizer::skeleton($trading),
                'registration_hash' => IdentityNormalizer::registrationHash($v->registration_number, $v->registration_country),
                'registration_country' => IdentityNormalizer::name($v->registration_country),
                'website_host' => IdentityNormalizer::host($v->website ?: $profile?->website),
                'email_domain' => $v->official_email_confirmed_at ? IdentityNormalizer::emailDomain($v->official_email) : null,
                'logo_hash' => $logo?->meta['dhash'] ?? null,
                'cover_hash' => $cover?->meta['dhash'] ?? null,
            ]);
        });
    }

    /** Every business that is verified now has an active protected identity (self-healing; idempotent). @return int identities created */
    public function ensureProtected(): int
    {
        $made = 0;

        BusinessVerification::where('status', BusinessVerification::VERIFIED)->where('expires_at', '>', now())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('verified_identities')->whereColumn('verified_identities.team_id', 'business_verifications.team_id')->where('active', true))
            ->orderBy('id')->each(function (BusinessVerification $v) use (&$made) {
                $this->protect($v);
                $made++;
            });

        return $made;
    }

    public function unprotect(Team $team): void
    {
        VerifiedIdentity::where('team_id', $team->id)->where('active', true)->update(['active' => false]);
    }

    /** A verified business changed its logo or cover: protect the new branding. */
    public function refreshBranding(Team $team): bool
    {
        $identity = VerifiedIdentity::where('team_id', $team->id)->where('active', true)->first();

        if (! $identity) {
            return false;
        }

        $identity->forceFill([
            'logo_hash' => $team->currentMedia('logo')?->meta['dhash'] ?? $identity->logo_hash,
            'cover_hash' => $team->currentMedia('cover')?->meta['dhash'] ?? $identity->cover_hash,
        ])->save();

        return true;
    }

    // ------------------------------------------------------------------------------------------ detection

    /**
     * Compare an account with every protected identity. $claims are details being claimed right
     * now that are not yet on the profile (a verification application's fields).
     *
     * @param  array{registered_name?: string|null, trading_name?: string|null, registration_number?: string|null, registration_country?: string|null, website?: string|null, official_email?: string|null}  $claims
     */
    public function evaluate(Team $subject, string $trigger, array $claims = []): ?IdentityConflict
    {
        if ($subject->personal_team) {
            return null;
        }

        $facts = $this->facts($subject, $claims);
        $worst = null;

        $identities = VerifiedIdentity::where('active', true)->where('team_id', '!=', $subject->id)->with('team')->get();

        foreach ($identities as $identity) {
            if ($this->related($subject, $identity->team)) {
                continue;
            }

            $signals = $this->signals($facts, $identity);
            $score = array_sum(array_column($signals, 'weight'));
            $level = $this->level($score);

            if ($level === null) {
                continue;
            }

            $conflict = $this->record($subject, $identity, $signals, $score, $level, $trigger);
            if ($conflict && (! $worst || $conflict->score > $worst->score)) {
                $worst = $conflict;
            }
        }

        return $worst;
    }

    /** The open conflict that stops this account from being verified, if any. */
    public function blockingConflict(Team $team): ?IdentityConflict
    {
        return IdentityConflict::where('subject_team_id', $team->id)
            ->whereIn('status', ['open', 'escalated'])->where('level', '!=', 'low')
            ->orderByDesc('score')->first();
    }

    // ------------------------------------------------------------------------------------------ reviewers

    public function canReview(User $user, IdentityConflict $conflict): bool
    {
        return $user->isModerator()
            && ! $user->belongsToTeam($conflict->subject)
            && ! $user->belongsToTeam($conflict->protected);
    }

    public function resolve(User $reviewer, IdentityConflict $conflict, string $action, string $note, ?string $kind = null): void
    {
        abort_unless($this->canReview($reviewer, $conflict), 403, 'Identity conflicts are decided by an InfoDot reviewer who belongs to neither business.');

        if (! in_array($conflict->status, ['open', 'escalated'], true)) {
            throw ValidationException::withMessages(['status' => 'This conflict was already resolved.']);
        }

        DB::transaction(function () use ($reviewer, $conflict, $action, $note, $kind) {
            if ($action === 'relate') {
                if (! array_key_exists((string) $kind, BusinessRelationship::KINDS)) {
                    throw ValidationException::withMessages(['kind' => 'Say how the two businesses are related.']);
                }

                BusinessRelationship::create([
                    'team_id' => $conflict->subject_team_id,
                    'parent_team_id' => $conflict->protected_team_id,
                    'kind' => $kind,
                    'status' => 'confirmed',
                    'note' => $note,
                    'decided_by' => $reviewer->id,
                    'decided_at' => now(),
                ]);
            }

            $conflict->forceFill([
                'status' => ['dismiss' => 'dismissed', 'relate' => 'related', 'escalate' => 'escalated'][$action],
                'resolved_by' => $reviewer->id,
                'resolved_at' => $action === 'escalate' ? null : now(),
                'resolution_note' => $note,
            ])->save();
        });
    }

    // ------------------------------------------------------------------------------------------ relationships

    public function claim(User $by, Team $team, Team $parent, string $kind, ?string $note): BusinessRelationship
    {
        if ($team->is($parent)) {
            throw ValidationException::withMessages(['code' => 'That is this business itself.']);
        }

        if (! VerifiedIdentity::where('team_id', $parent->id)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['code' => 'Only a verified business can confirm a relationship. That business is not verified.']);
        }

        $existing = BusinessRelationship::where('team_id', $team->id)->where('parent_team_id', $parent->id)->whereIn('status', ['pending', 'confirmed'])->first();
        if ($existing) {
            throw ValidationException::withMessages(['code' => $existing->status === 'confirmed' ? 'That relationship is already confirmed.' : 'A request to that business is already waiting.']);
        }

        $relationship = BusinessRelationship::create([
            'team_id' => $team->id, 'parent_team_id' => $parent->id, 'kind' => $kind,
            'status' => 'pending', 'note' => $note, 'requested_by' => $by->id,
        ]);

        $this->tell($parent, UserNotice::RELATIONSHIP_REQUEST, 'br:'.$relationship->id.':pending', $by);

        return $relationship;
    }

    public function decide(User $by, BusinessRelationship $relationship, bool $confirm): void
    {
        $parent = $relationship->parent()->firstOrFail();
        abort_unless($by->ownsTeam($parent) || $by->hasTeamRole($parent, 'admin'), 403);

        if ($relationship->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'That request was already answered.']);
        }

        DB::transaction(function () use ($by, $relationship, $confirm) {
            $relationship->forceFill(['status' => $confirm ? 'confirmed' : 'rejected', 'decided_by' => $by->id, 'decided_at' => now()])->save();

            if ($confirm) {
                IdentityConflict::whereIn('status', ['open', 'escalated'])
                    ->where(fn ($q) => $q->where(fn ($q) => $q->where('subject_team_id', $relationship->team_id)->where('protected_team_id', $relationship->parent_team_id))
                        ->orWhere(fn ($q) => $q->where('subject_team_id', $relationship->parent_team_id)->where('protected_team_id', $relationship->team_id)))
                    ->update([
                        'status' => 'related',
                        'resolved_by' => $by->id,
                        'resolved_at' => now(),
                        'resolution_note' => 'Confirmed by the verified business as: '.strtolower(BusinessRelationship::KINDS[$relationship->kind] ?? $relationship->kind).'.',
                    ]);
            }
        });

        $this->tell($relationship->team()->firstOrFail(), UserNotice::RELATIONSHIP_DECIDED, 'br:'.$relationship->id.':'.$relationship->status, $by);
    }

    public function end(User $by, BusinessRelationship $relationship): void
    {
        $team = $relationship->team()->firstOrFail();
        $parent = $relationship->parent()->firstOrFail();
        abort_unless($by->ownsTeam($team) || $by->hasTeamRole($team, 'admin') || $by->ownsTeam($parent) || $by->hasTeamRole($parent, 'admin'), 403);

        $relationship->forceFill(['status' => 'ended', 'decided_by' => $by->id, 'decided_at' => now()])->save();
    }

    // ------------------------------------------------------------------------------------------ internals

    /** @return array{names: list<string>, registration: list<string>, hosts: list<string>, domains: list<string>, logo: ?string, cover: ?string, country: ?string} */
    private function facts(Team $team, array $claims): array
    {
        $profile = $team->profile()->first();
        $country = $claims['registration_country'] ?? $profile?->registration_country;

        $names = array_values(array_unique(array_filter(array_map([IdentityNormalizer::class, 'name'], [
            $team->name, $profile?->trading_name, $claims['registered_name'] ?? null, $claims['trading_name'] ?? null,
        ]))));

        return [
            'names' => $names,
            'registration' => array_values(array_unique(array_filter([
                IdentityNormalizer::registrationHash($profile?->registration_number, $profile?->registration_country),
                IdentityNormalizer::registrationHash($claims['registration_number'] ?? null, $country),
            ]))),
            'hosts' => array_values(array_unique(array_filter([IdentityNormalizer::host($profile?->website), IdentityNormalizer::host($claims['website'] ?? null)]))),
            'domains' => array_values(array_unique(array_filter([IdentityNormalizer::emailDomain($profile?->contact_email), IdentityNormalizer::emailDomain($claims['official_email'] ?? null)]))),
            'logo' => $team->currentMedia('logo')?->meta['dhash'] ?? null,
            'cover' => $team->currentMedia('cover')?->meta['dhash'] ?? null,
            'country' => IdentityNormalizer::name($country),
        ];
    }

    /** @return list<array{type: string, weight: int, detail: string}> */
    private function signals(array $facts, VerifiedIdentity $id): array
    {
        $w = (array) config('identity.weights');
        $signals = [];
        $add = function (string $type, string $detail) use (&$signals, $w) {
            $signals[] = ['type' => $type, 'weight' => (int) ($w[$type] ?? 0), 'detail' => $detail];
        };

        if ($id->registration_hash && in_array($id->registration_hash, $facts['registration'], true)) {
            $add('registration_number', 'Same registration number as the verified business');
        }

        // The strongest single name signal counts once.
        $best = null;
        foreach ($facts['names'] as $name) {
            foreach (array_filter([$id->name_key, $id->trading_key]) as $theirs) {
                $candidate = match (true) {
                    $name === $theirs => ['name_exact', 'Same name: “'.$name.'”'],
                    IdentityNormalizer::skeleton($name) === IdentityNormalizer::skeleton($theirs) => ['name_lookalike', 'Look-alike name: “'.$name.'” against “'.$theirs.'”'],
                    IdentityNormalizer::similarity($name, $theirs) >= (float) config('identity.name_similarity') => ['name_similar', 'Similar name: “'.$name.'” against “'.$theirs.'”'],
                    default => null,
                };
                if ($candidate && (! $best || ($w[$candidate[0]] ?? 0) > ($w[$best[0]] ?? 0))) {
                    $best = $candidate;
                }
            }
        }
        if ($best) {
            $add($best[0], $best[1]);
        }

        if ($id->website_host && in_array($id->website_host, $facts['hosts'], true)) {
            $add('website', 'Same website: '.$id->website_host);
        }

        $theirDomains = array_filter([$id->website_host, $id->email_domain]);
        if (array_intersect($facts['domains'], $theirDomains) !== []) {
            $add('contact_domain', 'Contact email at the verified business’s domain');
        }

        $limit = (int) config('identity.image_distance');
        if ($id->logo_hash && $facts['logo'] && ImageFingerprint::distance($id->logo_hash, $facts['logo']) <= $limit) {
            $add('logo', 'Near-identical logo ('.ImageFingerprint::distance($id->logo_hash, $facts['logo']).' of 64 bits differ)');
        }
        if ($id->cover_hash && $facts['cover'] && ImageFingerprint::distance($id->cover_hash, $facts['cover']) <= $limit) {
            $add('cover', 'Near-identical cover photograph ('.ImageFingerprint::distance($id->cover_hash, $facts['cover']).' of 64 bits differ)');
        }

        if ($signals !== [] && $facts['country'] && $facts['country'] === $id->registration_country) {
            $add('same_country', 'Same country of registration');
        }

        return $signals;
    }

    private function level(int $score): ?string
    {
        foreach ((array) config('identity.levels') as $level => $min) {
            if ($score >= $min) {
                return $level;
            }
        }

        return null;
    }

    /** The verified business's own owner, its administrators, or a confirmed relationship: expected, not suspicious. */
    private function related(Team $subject, Team $protected): bool
    {
        $owner = User::find($subject->user_id);
        if ($owner && ($owner->ownsTeam($protected) || $owner->hasTeamRole($protected, 'admin'))) {
            return true;
        }

        return BusinessRelationship::where('status', 'confirmed')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('team_id', $subject->id)->where('parent_team_id', $protected->id))
                ->orWhere(fn ($q) => $q->where('team_id', $protected->id)->where('parent_team_id', $subject->id)))
            ->exists();
    }

    /** @param list<array{type: string, weight: int, detail: string}> $signals */
    private function record(Team $subject, VerifiedIdentity $identity, array $signals, int $score, string $level, string $trigger): ?IdentityConflict
    {
        $open = IdentityConflict::where('subject_team_id', $subject->id)->where('protected_team_id', $identity->team_id)->whereIn('status', ['open', 'escalated'])->first();

        if (! $open) {
            // A reviewer already looked at this pair: only something stronger reopens it.
            $resolved = IdentityConflict::where('subject_team_id', $subject->id)->where('protected_team_id', $identity->team_id)->whereIn('status', ['dismissed', 'related'])->orderByDesc('id')->first();
            if ($resolved && $score <= $resolved->score) {
                return null;
            }
        }

        $before = $open?->level;
        $conflict = $open ?? new IdentityConflict(['subject_team_id' => $subject->id, 'protected_team_id' => $identity->team_id, 'status' => 'open']);
        $conflict->fill([
            'verified_identity_id' => $identity->id,
            'signals' => $signals,
            'score' => $score,
            'level' => $level,
            'trigger' => $trigger,
            'last_seen_at' => now(),
        ])->save();

        if ($level !== 'low' && $before !== $level) {
            $this->alert($conflict);
        }

        return $conflict;
    }

    private function alert(IdentityConflict $conflict): void
    {
        $ref = 'ic:'.$conflict->id.':'.$conflict->level;

        User::where('is_moderator', true)->get()
            ->reject(fn (User $u) => $u->belongsToTeam($conflict->subject) || $u->belongsToTeam($conflict->protected))
            ->each(fn (User $u) => $this->notices->record(UserNotice::IDENTITY_CONFLICT, $u, 'business', $conflict->protected_team_id, $ref));

        if (in_array($conflict->level, ['high', 'critical'], true)) {
            $this->tell($conflict->protected()->firstOrFail(), UserNotice::IDENTITY_ALERT, $ref);
        }
    }

    /** The business's owner and administrators. */
    private function tell(Team $team, string $kind, string $ref, ?User $actor = null): void
    {
        $this->administrators($team)->each(fn (User $u) => $this->notices->record($kind, $u, 'business', $team->id, $ref, $actor));
    }

    /** @return Collection<int, User> */
    private function administrators(Team $team): Collection
    {
        return $team->allUsers()->filter(fn (User $u) => $u->ownsTeam($team) || $u->hasTeamRole($team, 'admin'))->values();
    }
}
