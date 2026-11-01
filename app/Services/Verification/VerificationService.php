<?php

namespace App\Services\Verification;

use App\Mail\VerificationEmailCode;
use App\Models\Attachment;
use App\Models\BusinessVerification;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Models\VerificationEvent;
use App\Services\Attachments\AttachmentService;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only way a business verification changes (R-29 Part B, §26 "verification decisions must be
 * made by authorised processes"). Every transition is checked against the current status, writes
 * an append-only VerificationEvent and tells the people concerned.
 *
 * What InfoDot can honestly confirm, and the rules for granting the badge:
 *  - approval needs config('verification.minimum_checks') confirmed checks, one of them a review of
 *    registration or founding documents, and at least one evidence document on file;
 *  - "official email" can only be counted once the code sent to that address was entered;
 *  - the reviewer must be a moderator who is not a member of the business;
 *  - nothing is checked against a registry or by telephone — neither is connected.
 */
class VerificationService
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly InAppNotices $notices,
    ) {}

    // ------------------------------------------------------------------------------------------ applicants

    /**
     * @param  array<string, mixed>  $data  validated application fields
     * @param  array<int, array{file: UploadedFile, label: string}>  $evidence
     */
    public function apply(User $applicant, Team $team, array $data, array $evidence): BusinessVerification
    {
        if ($team->personal_team) {
            throw ValidationException::withMessages(['team' => 'A personal workspace is not a business and cannot be verified.']);
        }

        if ($team->openVerification()) {
            throw ValidationException::withMessages(['team' => 'An application for this business is already with InfoDot.']);
        }

        $standing = $team->standing;
        if ($standing?->status === BusinessVerification::SUSPENDED) {
            throw ValidationException::withMessages(['team' => 'This business’s verification is suspended. InfoDot will contact you; a new application cannot be made meanwhile.']);
        }

        $window = now()->addDays((int) config('verification.renewal_warning_days'));
        if ($standing?->isCurrent() && $standing->expires_at?->greaterThan($window)) {
            throw ValidationException::withMessages(['team' => 'This business is verified until '.$standing->expires_at->format('j F Y').'. Renewal opens '.$standing->expires_at->copy()->subDays((int) config('verification.renewal_warning_days'))->format('j F Y').'.']);
        }

        $this->assertEvidenceCount(count($evidence), 0);

        return DB::transaction(function () use ($applicant, $team, $data, $evidence) {
            $verification = new BusinessVerification($data);
            $verification->forceFill([
                'team_id' => $team->id,
                'applicant_id' => $applicant->id,
                'status' => BusinessVerification::PENDING,
                'declared_at' => now(),
            ])->save();

            $this->storeEvidence($applicant, $verification, $evidence);
            $this->log($verification, 'submitted', null, BusinessVerification::PENDING, $applicant);
            $this->tellModerators($verification);

            return $verification;
        });
    }

    /** @param array<int, array{file: UploadedFile, label: string}> $evidence */
    public function addEvidence(User $applicant, BusinessVerification $verification, array $evidence): void
    {
        $this->expect($verification, [BusinessVerification::PENDING, BusinessVerification::INFO_REQUIRED, BusinessVerification::UNDER_REVIEW]);
        $this->assertEvidenceCount(count($evidence), $verification->evidence()->count());

        DB::transaction(function () use ($applicant, $verification, $evidence) {
            $this->storeEvidence($applicant, $verification, $evidence);
            $this->log($verification, 'evidence_added', $verification->status, $verification->status, $applicant);
        });
    }

    /** The applicant answered a request for more information: back to the reviewers. */
    public function resubmit(User $applicant, BusinessVerification $verification): void
    {
        $this->move($verification, [BusinessVerification::INFO_REQUIRED], BusinessVerification::PENDING, 'info_provided', $applicant);
        $this->tellModerators($verification);
    }

    public function withdraw(User $applicant, BusinessVerification $verification): void
    {
        $this->move($verification, BusinessVerification::OPEN, BusinessVerification::WITHDRAWN, 'withdrawn', $applicant, extra: ['decided_at' => now()]);
    }

    /** Send a one-time code to an address at the business's own website domain (§10B). */
    public function sendEmailCode(User $applicant, BusinessVerification $verification, string $email): void
    {
        $this->expect($verification, BusinessVerification::OPEN);
        $email = strtolower(trim($email));
        $domain = Str::after($email, '@');
        $host = $verification->websiteHost();

        if ($host === null) {
            throw ValidationException::withMessages(['official_email' => 'Add the business’s website to the application first: the address must sit at that domain.']);
        }

        if (in_array($domain, (array) config('verification.generic_email_domains'), true)) {
            throw ValidationException::withMessages(['official_email' => 'A '.$domain.' address can belong to anyone, so it cannot show the business controls its domain. Use an address at '.$host.'.']);
        }

        if ($domain !== $host && ! str_ends_with($domain, '.'.$host)) {
            throw ValidationException::withMessages(['official_email' => 'The address must be at the business’s website domain, '.$host.'.']);
        }

        if ($verification->email_code_expires_at !== null
            && $verification->email_code_expires_at->copy()->subMinutes((int) config('verification.email_code.ttl_minutes'))->greaterThan(now()->subMinute())) {
            throw ValidationException::withMessages(['official_email' => 'A code was sent less than a minute ago. Check the inbox (and spam) before asking for another.']);
        }

        $code = (string) random_int(100000, 999999);
        $minutes = (int) config('verification.email_code.ttl_minutes');

        $verification->forceFill([
            'official_email' => $email,
            'official_email_confirmed_at' => null,
            'email_code_hash' => Hash::make($code),
            'email_code_expires_at' => now()->addMinutes($minutes),
            'email_code_attempts' => 0,
        ])->save();

        Mail::to($email)->send(new VerificationEmailCode($code, $verification->team->name, $minutes));
        $this->log($verification, 'email_code_sent', $verification->status, $verification->status, $applicant);
    }

    public function confirmEmailCode(User $applicant, BusinessVerification $verification, string $code): void
    {
        $this->expect($verification, BusinessVerification::OPEN);

        if ($verification->email_code_hash === null || $verification->email_code_expires_at?->isPast()) {
            throw ValidationException::withMessages(['code' => 'That code has expired. Ask for a new one.']);
        }

        if ($verification->email_code_attempts >= (int) config('verification.email_code.attempts')) {
            throw ValidationException::withMessages(['code' => 'Too many wrong codes. Ask for a new one.']);
        }

        if (! Hash::check(trim($code), $verification->email_code_hash)) {
            $verification->increment('email_code_attempts');
            throw ValidationException::withMessages(['code' => 'That is not the code we sent.']);
        }

        $verification->forceFill([
            'official_email_confirmed_at' => now(),
            'email_code_hash' => null,
            'email_code_expires_at' => null,
            'email_code_attempts' => 0,
        ])->save();
        $this->log($verification, 'email_confirmed', $verification->status, $verification->status, $applicant);
    }

    // ------------------------------------------------------------------------------------------ reviewers

    public function startReview(User $reviewer, BusinessVerification $verification): void
    {
        $this->assertReviewer($reviewer, $verification);
        $this->move($verification, [BusinessVerification::PENDING], BusinessVerification::UNDER_REVIEW, 'review_started', $reviewer);
    }

    public function requestInformation(User $reviewer, BusinessVerification $verification, string $message, ?string $note = null): void
    {
        $this->assertReviewer($reviewer, $verification);
        $this->move($verification, [BusinessVerification::PENDING, BusinessVerification::UNDER_REVIEW], BusinessVerification::INFO_REQUIRED, 'info_requested', $reviewer, $note, ['message_to_applicant' => $message]);
        $this->tellBusiness($verification);
    }

    /** @param list<string> $checks keys of BusinessVerification::CHECKS the reviewer confirmed */
    public function approve(User $reviewer, BusinessVerification $verification, array $checks, ?string $note = null): void
    {
        $this->assertReviewer($reviewer, $verification);
        $checks = $this->validChecks($verification, $checks);

        if ($verification->evidence()->count() === 0) {
            throw ValidationException::withMessages(['checks' => 'There is no evidence document on file. Ask for one before approving.']);
        }

        DB::transaction(function () use ($reviewer, $verification, $checks, $note) {
            $previous = $verification->team->standing;

            $this->move($verification, [BusinessVerification::PENDING, BusinessVerification::UNDER_REVIEW], BusinessVerification::VERIFIED, 'approved', $reviewer, $note, [
                'checks' => $checks,
                'decided_by' => $reviewer->id,
                'decided_at' => now(),
                'verified_at' => now(),
                'expires_at' => now()->addMonths((int) config('verification.valid_months')),
                'renewal_reminded_at' => null,
                'message_to_applicant' => null,
                'identity_snapshot' => $this->snapshot($verification->team),
            ]);

            // A renewal takes over from the standing it replaces.
            if ($previous && $previous->id !== $verification->id && in_array($previous->status, [BusinessVerification::VERIFIED, BusinessVerification::UNDER_REVIEW, BusinessVerification::EXPIRED], true)) {
                $this->move($previous, [$previous->status], BusinessVerification::SUPERSEDED, 'superseded', $reviewer);
            }
        });

        $this->tellBusiness($verification);
    }

    public function reject(User $reviewer, BusinessVerification $verification, string $message, ?string $note = null): void
    {
        $this->assertReviewer($reviewer, $verification);
        $this->move($verification, BusinessVerification::OPEN, BusinessVerification::REJECTED, 'rejected', $reviewer, $note, [
            'message_to_applicant' => $message,
            'decided_by' => $reviewer->id,
            'decided_at' => now(),
        ]);
        $this->tellBusiness($verification);
    }

    /** A verified business whose details changed is confirmed again, keeping its expiry. */
    public function reconfirm(User $reviewer, BusinessVerification $standing, ?string $note = null): void
    {
        $this->assertReviewer($reviewer, $standing);
        $this->assertStanding($standing);
        $this->move($standing, [BusinessVerification::UNDER_REVIEW], $standing->expires_at?->isFuture() ? BusinessVerification::VERIFIED : BusinessVerification::EXPIRED, 'reconfirmed', $reviewer, $note, [
            'identity_snapshot' => $this->snapshot($standing->team),
            'decided_by' => $reviewer->id,
            'decided_at' => now(),
        ]);
        $this->tellBusiness($standing);
    }

    public function suspend(User $reviewer, BusinessVerification $standing, string $message, ?string $note = null): void
    {
        $this->assertReviewer($reviewer, $standing);
        $this->assertStanding($standing);
        $this->move($standing, [BusinessVerification::VERIFIED, BusinessVerification::UNDER_REVIEW], BusinessVerification::SUSPENDED, 'suspended', $reviewer, $note, ['message_to_applicant' => $message]);
        $this->tellBusiness($standing);
    }

    public function reinstate(User $reviewer, BusinessVerification $standing, ?string $note = null): void
    {
        $this->assertReviewer($reviewer, $standing);
        $this->assertStanding($standing);
        $to = $standing->expires_at?->isFuture() ? BusinessVerification::VERIFIED : BusinessVerification::EXPIRED;
        $this->move($standing, [BusinessVerification::SUSPENDED], $to, 'reinstated', $reviewer, $note, ['message_to_applicant' => null]);
        $this->tellBusiness($standing);
    }

    public function revoke(User $reviewer, BusinessVerification $standing, string $message, ?string $note = null): void
    {
        $this->assertReviewer($reviewer, $standing);
        $this->assertStanding($standing);
        $this->move($standing, [BusinessVerification::VERIFIED, BusinessVerification::SUSPENDED, BusinessVerification::UNDER_REVIEW], BusinessVerification::REVOKED, 'revoked', $reviewer, $note, [
            'message_to_applicant' => $message,
            'decided_by' => $reviewer->id,
            'decided_at' => now(),
        ]);
        $this->tellBusiness($standing);
    }

    /** Whether this person may decide on this business's verification. */
    public function canReview(User $user, Team $team): bool
    {
        return $user->isModerator() && ! $user->belongsToTeam($team);
    }

    // ------------------------------------------------------------------------------------------ system

    /**
     * A verified business changed what it is called, its registration details or its website:
     * the badge comes off until a reviewer confirms the new details (§13 reverification).
     */
    public function identityChanged(Team $team): void
    {
        $standing = $team->standing()->first();

        if (! $standing || $standing->status !== BusinessVerification::VERIFIED || ! is_array($standing->identity_snapshot)) {
            return;
        }

        $now = $this->snapshot($team);
        $changed = array_keys(array_filter($now, fn ($value, $key) => ($standing->identity_snapshot[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed === []) {
            return;
        }

        $this->move($standing, [BusinessVerification::VERIFIED], BusinessVerification::UNDER_REVIEW, 'identity_changed', null, 'Changed: '.implode(', ', $changed));
        $this->tellBusiness($standing);
        $this->tellModerators($standing);
    }

    /** @return array{expired: int, reminded: int} */
    public function sweepExpiry(): array
    {
        $expired = 0;
        $reminded = 0;

        BusinessVerification::where('status', BusinessVerification::VERIFIED)->where('expires_at', '<=', now())
            ->orderBy('id')->each(function (BusinessVerification $v) use (&$expired) {
                $this->move($v, [BusinessVerification::VERIFIED], BusinessVerification::EXPIRED, 'expired', null);
                $this->tellBusiness($v);
                $expired++;
            });

        BusinessVerification::where('status', BusinessVerification::VERIFIED)->whereNull('renewal_reminded_at')
            ->where('expires_at', '<=', now()->addDays((int) config('verification.renewal_warning_days')))
            ->orderBy('id')->each(function (BusinessVerification $v) use (&$reminded) {
                $v->forceFill(['renewal_reminded_at' => now()])->save();
                $this->notify($v, UserNotice::BUSINESS_VERIFICATION_RENEWAL);
                $reminded++;
            });

        return ['expired' => $expired, 'reminded' => $reminded];
    }

    /** Delete evidence that is no longer needed (§25 defined retention, POPIA s14). @return int applications purged */
    public function purgeEvidence(): int
    {
        $cutoff = now()->subDays((int) config('verification.evidence_retention_days'));
        $done = 0;

        BusinessVerification::whereNull('evidence_purged_at')
            ->whereIn('status', [BusinessVerification::REJECTED, BusinessVerification::WITHDRAWN, BusinessVerification::REVOKED, BusinessVerification::EXPIRED, BusinessVerification::SUPERSEDED])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')->each(function (BusinessVerification $v) use (&$done) {
                $v->evidence()->get()->each(fn (Attachment $a) => $a->delete());
                $v->forceFill(['evidence_purged_at' => now(), 'registration_number' => null, 'phone' => null, 'address' => null])->saveQuietly();
                $this->log($v, 'evidence_purged', $v->status, $v->status, null);
                $done++;
            });

        return $done;
    }

    // ------------------------------------------------------------------------------------------ internals

    /**
     * @param  list<string>  $from
     * @param  array<string, mixed>  $extra
     */
    private function move(BusinessVerification $v, array $from, string $to, string $event, ?User $actor, ?string $note = null, array $extra = []): void
    {
        DB::transaction(function () use ($v, $from, $to, $event, $actor, $note, $extra) {
            $locked = BusinessVerification::whereKey($v->id)->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['status' => 'This application has already moved on ('.strtolower($locked?->label() ?? 'gone').'). Refresh to see where it is now.']);
            }

            $was = $locked->status;
            $v->forceFill(['status' => $to] + $extra)->save();
            $this->log($v, $event, $was, $to, $actor, $note);
        });
    }

    /** @param list<string> $statuses */
    private function expect(BusinessVerification $v, array $statuses): void
    {
        if (! in_array($v->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => 'This application is '.strtolower($v->label()).' and can no longer be changed.']);
        }
    }

    private function assertReviewer(User $reviewer, BusinessVerification $v): void
    {
        if (! $this->canReview($reviewer, $v->team)) {
            abort(403, 'Verification is decided by an InfoDot reviewer who is not part of the business.');
        }
    }

    private function assertStanding(BusinessVerification $v): void
    {
        if ($v->verified_at === null) {
            throw ValidationException::withMessages(['status' => 'Only a business that was verified can be suspended, reinstated, reconfirmed or revoked.']);
        }
    }

    /**
     * @param  list<string>  $checks
     * @return list<string>
     */
    private function validChecks(BusinessVerification $v, array $checks): array
    {
        $checks = array_values(array_intersect(array_keys(BusinessVerification::CHECKS), $checks));

        if (in_array('official_email', $checks, true) && $v->official_email_confirmed_at === null) {
            throw ValidationException::withMessages(['checks' => 'The official email was not confirmed with a code, so it cannot be counted.']);
        }

        if (! in_array('registration_document', $checks, true)) {
            throw ValidationException::withMessages(['checks' => 'A review of registration or founding documents is required for every approval.']);
        }

        if (count($checks) < (int) config('verification.minimum_checks')) {
            throw ValidationException::withMessages(['checks' => 'Approval needs at least '.config('verification.minimum_checks').' confirmed checks — one signal on its own is not enough.']);
        }

        return $checks;
    }

    private function assertEvidenceCount(int $adding, int $have): void
    {
        $max = (int) config('verification.max_evidence_files');

        if ($have + $adding === 0) {
            throw ValidationException::withMessages(['evidence' => 'Attach at least one document — for example the registration certificate.']);
        }

        if ($have + $adding > $max) {
            throw ValidationException::withMessages(['evidence' => 'At most '.$max.' documents per application.']);
        }
    }

    /** @param array<int, array{file: UploadedFile, label: string}> $evidence */
    private function storeEvidence(User $applicant, BusinessVerification $v, array $evidence): void
    {
        foreach ($evidence as $i => $item) {
            $attachment = $this->attachments->upload($applicant, $item['file']);

            if (! in_array($attachment->kind, [Attachment::PDF, Attachment::IMAGE], true)) {
                $attachment->delete();
                throw ValidationException::withMessages(['evidence' => 'Evidence must be a PDF or an image (a scan or photograph of the document).']);
            }

            $attachment->forceFill([
                'attachable_type' => $v->getMorphClass(),
                'attachable_id' => $v->id,
                'team_id' => $v->team_id,
                'role' => 'evidence',
                'caption' => Str::limit($item['label'], 120, ''),
                'sort_order' => $i,
                'expires_at' => null,
            ])->save();
        }
    }

    /** The public identity a verification vouched for. The registration number is kept as a hash only. */
    private function snapshot(Team $team): array
    {
        $profile = $team->profile()->first();
        $norm = fn (?string $s) => $s === null || trim($s) === '' ? null : mb_strtolower(preg_replace('/\s+/', ' ', trim($s)) ?? '');
        $reg = $norm(preg_replace('/[^A-Za-z0-9]/', '', (string) $profile?->registration_number));
        $host = $profile?->website ? parse_url($profile->website, PHP_URL_HOST) : null;

        return [
            'name' => $norm($team->name),
            'trading_name' => $norm($profile?->trading_name),
            'registration_number' => $reg ? hash('sha256', $reg) : null,
            'registration_country' => $norm($profile?->registration_country),
            'website' => is_string($host) ? preg_replace('/^www\./', '', strtolower($host)) : null,
        ];
    }

    private function log(BusinessVerification $v, string $event, ?string $from, ?string $to, ?User $actor, ?string $note = null): void
    {
        VerificationEvent::create([
            'business_verification_id' => $v->id,
            'team_id' => $v->team_id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /** The business's owner and administrators, and whoever applied. */
    private function tellBusiness(BusinessVerification $v): void
    {
        $this->notify($v, UserNotice::BUSINESS_VERIFICATION);
    }

    private function notify(BusinessVerification $v, string $kind): void
    {
        $team = $v->team()->first();
        if (! $team) {
            return;
        }

        $people = $team->allUsers()->filter(fn (User $u) => $u->ownsTeam($team) || $u->hasTeamRole($team, 'admin'));
        if ($v->applicant_id && ! $people->contains('id', $v->applicant_id) && ($applicant = User::find($v->applicant_id))) {
            $people->push($applicant);
        }

        foreach ($people as $person) {
            $this->notices->record($kind, $person, 'business', $team->id, 'bv:'.$v->id.':'.$v->status.':'.($v->updated_at?->getTimestamp() ?? 0));
        }
    }

    private function tellModerators(BusinessVerification $v): void
    {
        User::where('is_moderator', true)->get()
            ->reject(fn (User $u) => $u->belongsToTeam($v->team))
            ->each(fn (User $u) => $this->notices->record(UserNotice::VERIFICATION_QUEUE, $u, 'business', $v->team_id, 'vq:'.$v->id.':'.$v->status.':'.($v->updated_at?->getTimestamp() ?? 0)));
    }
}
