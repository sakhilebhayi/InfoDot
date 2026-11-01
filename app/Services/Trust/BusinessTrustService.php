<?php

namespace App\Services\Trust;

use App\Models\Attachment;
use App\Models\BusinessAppeal;
use App\Models\BusinessInvestigation;
use App\Models\BusinessInvestigationEvent;
use App\Models\BusinessReport;
use App\Models\BusinessVerification;
use App\Models\IdentityConflict;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Attachments\AttachmentService;
use App\Services\Knowledge\InAppNotices;
use App\Services\Verification\VerificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reports, investigations, restrictions and appeals for businesses (R-33 §7, R-29 Part D).
 * The rules, in one place:
 *  - a report is a reason to look; it changes nothing about the business by itself;
 *  - only a reviewer outside the business opens, restricts or concludes an investigation, and
 *    every step is written to its trail with who took it;
 *  - "confirmed" is only ever a recorded outcome, and the public never sees any of it — a
 *    restricted business is simply absent from discovery;
 *  - the business is told what affects it and may appeal once per decision, to a reviewer who
 *    did not make that decision.
 */
final class BusinessTrustService
{
    public const REPORTS_PER_DAY = 5;

    public const MAX_FILES = 3;

    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly InAppNotices $notices,
        private readonly VerificationService $verification,
    ) {}

    // ------------------------------------------------------------------ reports

    /** Why this person cannot report this business now, or null when they can. */
    public function whyNotReport(User $user, Team $team): ?string
    {
        if ($user->belongsToTeam($team)) {
            return 'You belong to '.$team->name.'. If something about it is wrong, fix it on its profile.';
        }

        if (BusinessReport::where('team_id', $team->id)->where('reporter_id', $user->id)->whereIn('status', [BusinessReport::OPEN, BusinessReport::INVESTIGATING])->exists()) {
            return 'You already reported '.$team->name.' and InfoDot is still looking at it. You will be told when it has been reviewed.';
        }

        if (BusinessReport::where('reporter_id', $user->id)->where('created_at', '>=', now()->subDay())->count() >= self::REPORTS_PER_DAY) {
            return 'You have sent '.self::REPORTS_PER_DAY.' reports in the last 24 hours, which is the most InfoDot takes from one person. Try again tomorrow.';
        }

        return null;
    }

    /**
     * @param  array{category: string, detail: string}  $data
     * @param  list<UploadedFile>  $files
     */
    public function report(User $reporter, Team $team, array $data, array $files = []): BusinessReport
    {
        if ($reason = $this->whyNotReport($reporter, $team)) {
            throw ValidationException::withMessages(['detail' => $reason]);
        }

        $report = DB::transaction(function () use ($reporter, $team, $data, $files) {
            $report = new BusinessReport($data);
            $report->team_id = $team->id;
            $report->reporter_id = $reporter->id;
            $report->priority = $this->priority($team, $data['category'], $reporter);
            $report->status = BusinessReport::OPEN;
            $report->save();
            $this->attach($report, $reporter, $files);

            return $report;
        });

        $this->tellReviewers($team, 'report:'.$report->id);

        return $report;
    }

    /** Close a report without an investigation: no problem found, or handled directly. */
    public function resolveReport(User $reviewer, BusinessReport $report, bool $actioned, string $note): void
    {
        $this->assertReviewer($reviewer, $report->team);
        abort_unless($report->status === BusinessReport::OPEN, 422);

        $report->forceFill([
            'status' => $actioned ? BusinessReport::ACTIONED : BusinessReport::DISMISSED,
            'resolved_by' => $reviewer->id,
            'resolution_note' => $note,
            'resolved_at' => now(),
        ])->save();

        $this->tellReporter($report);
    }

    // ------------------------------------------------------------------ investigations

    /**
     * Open an investigation of a business, from reports or an escalated identity conflict.
     *
     * @param  list<int>  $reportIds
     */
    public function open(User $reviewer, Team $team, string $concern, string $summary, array $reportIds = [], bool $restrict = false, ?string $messageToBusiness = null, ?IdentityConflict $conflict = null): BusinessInvestigation
    {
        $this->assertReviewer($reviewer, $team);
        abort_unless(array_key_exists($concern, BusinessInvestigation::CONCERNS), 422);

        if (BusinessInvestigation::where('team_id', $team->id)->where('status', BusinessInvestigation::OPEN)->exists()) {
            throw ValidationException::withMessages(['summary' => $team->name.' already has an open investigation. Link this to it instead.']);
        }

        $investigation = DB::transaction(function () use ($reviewer, $team, $concern, $summary, $reportIds, $messageToBusiness, $conflict) {
            $inv = new BusinessInvestigation(['concern' => $concern, 'summary' => $summary, 'message_to_business' => $messageToBusiness ?: null]);
            $inv->team_id = $team->id;
            $inv->status = BusinessInvestigation::OPEN;
            $inv->opened_by = $reviewer->id;
            $inv->identity_conflict_id = $conflict?->id;
            $inv->save();
            $this->event($inv, $reviewer, 'opened', $summary);

            foreach (BusinessReport::where('team_id', $team->id)->whereIn('id', $reportIds)->where('status', BusinessReport::OPEN)->get() as $report) {
                $report->forceFill(['status' => BusinessReport::INVESTIGATING, 'investigation_id' => $inv->id])->save();
                $this->event($inv, $reviewer, 'report_linked', '#'.$report->id.' '.$report->categoryLabel());
            }

            if ($messageToBusiness) {
                $this->event($inv, $reviewer, 'business_told', $messageToBusiness);
            }

            return $inv;
        });

        if ($restrict) {
            $this->restrict($reviewer, $investigation);
        } elseif ($messageToBusiness) {
            $this->tellBusiness($investigation, 'opened');
        }

        return $investigation;
    }

    public function linkReport(User $reviewer, BusinessInvestigation $inv, BusinessReport $report): void
    {
        $this->assertReviewer($reviewer, $inv->team);
        abort_unless($inv->isOpen() && $report->team_id === $inv->team_id && $report->status === BusinessReport::OPEN, 422);

        $report->forceFill(['status' => BusinessReport::INVESTIGATING, 'investigation_id' => $inv->id])->save();
        $this->event($inv, $reviewer, 'report_linked', '#'.$report->id.' '.$report->categoryLabel());
    }

    public function note(User $reviewer, BusinessInvestigation $inv, string $note): void
    {
        $this->assertReviewer($reviewer, $inv->team);
        $this->event($inv, $reviewer, 'note', $note);
    }

    /** Write to the business: what InfoDot is looking at, and what it can send. */
    public function message(User $reviewer, BusinessInvestigation $inv, string $message): void
    {
        $this->assertReviewer($reviewer, $inv->team);
        $inv->forceFill(['message_to_business' => $message])->save();
        $this->event($inv, $reviewer, 'business_told', $message);
        $this->tellBusiness($inv, 'message:'.now()->getTimestamp());
    }

    /** Take the business out of discovery while it is investigated. The business is told why; nobody else is told anything. */
    public function restrict(User $reviewer, BusinessInvestigation $inv): void
    {
        $this->assertReviewer($reviewer, $inv->team);
        abort_unless($inv->isOpen() || in_array($inv->status, BusinessInvestigation::CONFIRMED, true), 422);

        DB::transaction(function () use ($reviewer, $inv) {
            $inv->forceFill(['restricted' => true])->save();
            $inv->team->profileOrNew()->forceFill(['restricted_at' => $inv->team->profile->restricted_at ?? now()])->save();
            $this->event($inv, $reviewer, 'restricted');
        });

        $this->tellBusiness($inv, 'restricted');
    }

    public function lift(User $reviewer, BusinessInvestigation $inv, string $why = 'lifted'): void
    {
        $this->assertReviewer($reviewer, $inv->team);
        $this->liftQuietly($inv, $reviewer);
        $this->tellBusiness($inv, $why);
    }

    /** Record the outcome. Reports linked to it are closed and their reporters told, in general terms. */
    public function conclude(User $reviewer, BusinessInvestigation $inv, string $outcome, string $messageToBusiness): void
    {
        $this->assertReviewer($reviewer, $inv->team);
        abort_unless($inv->isOpen() && array_key_exists($outcome, BusinessInvestigation::STATUSES) && $outcome !== BusinessInvestigation::OPEN, 422);

        $confirmed = in_array($outcome, BusinessInvestigation::CONFIRMED, true);

        DB::transaction(function () use ($reviewer, $inv, $outcome, $messageToBusiness, $confirmed) {
            $inv->forceFill([
                'status' => $outcome,
                'message_to_business' => $messageToBusiness,
                'decided_by' => $reviewer->id,
                'decided_at' => now(),
            ])->save();
            $this->event($inv, $reviewer, 'concluded', BusinessInvestigation::STATUSES[$outcome].'. '.$messageToBusiness);

            foreach ($inv->reports()->where('status', BusinessReport::INVESTIGATING)->get() as $report) {
                $report->forceFill([
                    'status' => $outcome === BusinessInvestigation::CLEARED ? BusinessReport::DISMISSED : BusinessReport::ACTIONED,
                    'resolved_by' => $reviewer->id,
                    'resolution_note' => 'Investigation #'.$inv->id.': '.BusinessInvestigation::STATUSES[$outcome],
                    'resolved_at' => now(),
                ])->save();
                $this->tellReporter($report);
            }

            if ($confirmed) {
                // Confirmed: out of discovery, and any verification it holds is withdrawn.
                $inv->forceFill(['restricted' => true])->save();
                $inv->team->profileOrNew()->forceFill(['restricted_at' => $inv->team->profile->restricted_at ?? now()])->save();
                $standing = $inv->team->standing;
                if ($standing !== null && in_array($standing->status, [BusinessVerification::VERIFIED, BusinessVerification::SUSPENDED, BusinessVerification::UNDER_REVIEW], true)) {
                    $this->verification->revoke($reviewer, $standing, 'Withdrawn after an investigation: '.$messageToBusiness, 'Investigation #'.$inv->id);
                    $this->event($inv, $reviewer, 'verification_revoked');
                }
            } else {
                $this->liftQuietly($inv, $reviewer);
            }
        });

        $this->tellBusiness($inv, 'concluded:'.$outcome);
    }

    // ------------------------------------------------------------------ appeals

    /**
     * Decisions about this business that its owner or administrators may appeal now.
     *
     * @return list<BusinessInvestigation|BusinessVerification>
     */
    public function appealable(Team $team): array
    {
        $appealed = BusinessAppeal::where('team_id', $team->id)->get(['subject_type', 'subject_id'])
            ->map(fn (BusinessAppeal $a) => $a->subject_type.':'.$a->subject_id)->all();

        $investigations = BusinessInvestigation::where('team_id', $team->id)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', BusinessInvestigation::OPEN)->where('restricted', true))
                ->orWhereIn('status', BusinessInvestigation::CONFIRMED))
            ->get()->reject(fn ($i) => in_array('investigation:'.$i->id, $appealed, true));

        $verifications = BusinessVerification::where('team_id', $team->id)
            ->whereIn('status', [BusinessVerification::REJECTED, BusinessVerification::SUSPENDED, BusinessVerification::REVOKED])
            ->where('updated_at', '>=', now()->subDays(90))
            ->get()->reject(fn ($v) => in_array('verification:'.$v->id, $appealed, true));

        return [...$investigations->values()->all(), ...$verifications->values()->all()];
    }

    /** @param list<UploadedFile> $files */
    public function appeal(User $appellant, Team $team, string $subjectType, int $subjectId, string $body, array $files = []): BusinessAppeal
    {
        abort_unless($appellant->ownsTeam($team) || $appellant->hasTeamRole($team, 'admin'), 403);

        $subject = collect($this->appealable($team))->first(fn (Model $s) => ($s instanceof BusinessInvestigation ? 'investigation' : 'verification') === $subjectType && $s->getKey() === $subjectId);
        if ($subject === null) {
            throw ValidationException::withMessages(['body' => 'That decision cannot be appealed: it was already appealed, or it is not one about '.$team->name.'.']);
        }

        $appeal = DB::transaction(function () use ($appellant, $team, $subjectType, $subject, $body, $files) {
            $appeal = new BusinessAppeal(['body' => $body]);
            $appeal->team_id = $team->id;
            $appeal->appellant_id = $appellant->id;
            $appeal->subject_type = $subjectType;
            $appeal->subject_id = (int) $subject->getKey();
            $appeal->original_decider = $subject instanceof BusinessInvestigation ? ($subject->decided_by ?? $subject->opened_by) : $subject->decided_by;
            $appeal->status = BusinessAppeal::OPEN;
            $appeal->save();
            $this->attach($appeal, $appellant, $files);

            return $appeal;
        });

        $this->tellReviewers($team, 'appeal:'.$appeal->id);

        return $appeal;
    }

    /** Whether this reviewer may decide this appeal: outside the business, and not the person whose decision it is. */
    public function canDecideAppeal(User $reviewer, BusinessAppeal $appeal): bool
    {
        return $reviewer->isModerator() && ! $reviewer->belongsToTeam($appeal->team) && $reviewer->id !== $appeal->original_decider;
    }

    public function decideAppeal(User $reviewer, BusinessAppeal $appeal, bool $upheld, string $note): void
    {
        abort_unless($appeal->isOpen() && $this->canDecideAppeal($reviewer, $appeal), 403);
        $subject = $appeal->subject();

        DB::transaction(function () use ($reviewer, $appeal, $upheld, $note, $subject) {
            $appeal->forceFill([
                'status' => $upheld ? BusinessAppeal::UPHELD : BusinessAppeal::DENIED,
                'decided_by' => $reviewer->id,
                'decision_note' => $note,
                'decided_at' => now(),
            ])->save();

            if ($subject instanceof BusinessInvestigation) {
                $this->event($subject, $reviewer, $upheld ? 'appeal_upheld' : 'appeal_denied', $note);
                if ($upheld) {
                    // Cleared on appeal (scenario 8): back in discovery, the outcome recorded as cleared.
                    $subject->forceFill(['status' => BusinessInvestigation::CLEARED, 'decided_by' => $reviewer->id, 'decided_at' => now(), 'message_to_business' => $note])->save();
                    $this->liftQuietly($subject, $reviewer);
                }
            } elseif ($subject instanceof BusinessVerification && $upheld && $subject->status === BusinessVerification::SUSPENDED) {
                $this->verification->reinstate($reviewer, $subject, 'Appeal #'.$appeal->id.' upheld: '.$note);
            }
        });

        foreach ($this->managers($appeal->team) as $manager) {
            $this->notices->record(UserNotice::BUSINESS_STANDING, $manager, 'business', $appeal->team_id, 'bs:appeal:'.$appeal->id.':'.$appeal->status, $reviewer);
        }
    }

    // ------------------------------------------------------------------ internals

    private function priority(Team $team, string $category, User $reporter): string
    {
        $others = BusinessReport::where('team_id', $team->id)->whereIn('status', [BusinessReport::OPEN, BusinessReport::INVESTIGATING])
            ->where('reporter_id', '!=', $reporter->id)->distinct()->count('reporter_id');

        // Several different people raising the same business, or a serious claim against a business that holds a badge.
        if ($others >= 2 || (in_array($category, BusinessReport::SERIOUS, true) && $team->isVerified())) {
            return 'urgent';
        }

        return in_array($category, BusinessReport::SERIOUS, true) ? 'high' : 'normal';
    }

    private function liftQuietly(BusinessInvestigation $inv, User $reviewer): void
    {
        $wasRestricted = $inv->restricted || $inv->team->isRestricted();
        $inv->forceFill(['restricted' => false])->save();

        // Lift only if no other investigation still holds the business.
        $otherHold = BusinessInvestigation::where('team_id', $inv->team_id)->whereKeyNot($inv->id)
            ->where('restricted', true)->exists();
        if (! $otherHold && $inv->team->profile !== null) {
            $inv->team->profile->forceFill(['restricted_at' => null])->save();
        }

        if ($wasRestricted) {
            $this->event($inv, $reviewer, 'restriction_lifted');
        }
    }

    private function event(BusinessInvestigation $inv, User $actor, string $action, ?string $note = null): void
    {
        BusinessInvestigationEvent::create(['business_investigation_id' => $inv->id, 'user_id' => $actor->id, 'action' => $action, 'note' => $note, 'created_at' => now()]);
    }

    private function assertReviewer(User $reviewer, Team $team): void
    {
        abort_unless($this->verification->canReview($reviewer, $team), 403);
    }

    /** @param list<UploadedFile> $files */
    private function attach(Model $owner, User $uploader, array $files): void
    {
        if (count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['files' => 'Attach at most '.self::MAX_FILES.' files.']);
        }

        foreach ($files as $i => $file) {
            $attachment = $this->attachments->upload($uploader, $file);
            $attachment->forceFill([
                'attachable_type' => $owner->getMorphClass(),
                'attachable_id' => $owner->getKey(),
                'sort_order' => $i,
                'expires_at' => null,
            ])->save();
        }
    }

    /** @return Collection<int, User> */
    private function managers(Team $team): Collection
    {
        $admins = $team->users()->wherePivot('role', 'admin')->pluck('users.id');

        return User::query()->where('id', $team->user_id)->orWhereIn('id', $admins)->get()->values();
    }

    private function tellReviewers(Team $team, string $ref): void
    {
        User::where('is_moderator', true)->get()
            ->reject(fn (User $u) => $u->belongsToTeam($team))
            ->each(fn (User $u) => $this->notices->record(UserNotice::BUSINESS_TRUST_QUEUE, $u, 'business', $team->id, 'bt:'.$ref));
    }

    private function tellReporter(BusinessReport $report): void
    {
        if ($report->reporter !== null) {
            $this->notices->record(UserNotice::BUSINESS_REPORT_DECIDED, $report->reporter, 'business', $report->team_id, 'br:'.$report->id.':'.$report->status);
        }
    }

    private function tellBusiness(BusinessInvestigation $inv, string $what): void
    {
        foreach ($this->managers($inv->team) as $manager) {
            $this->notices->record(UserNotice::BUSINESS_STANDING, $manager, 'business', $inv->team_id, 'bs:inv:'.$inv->id.':'.$what);
        }
    }

    /** Files on a report or appeal, for the desk. @return \Illuminate\Support\Collection<int, Attachment> */
    public function files(Model $owner): Collection
    {
        return Attachment::where('attachable_type', $owner->getMorphClass())->where('attachable_id', $owner->getKey())->orderBy('sort_order')->get();
    }
}
