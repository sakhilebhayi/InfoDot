<?php

namespace App\Services\Enquiries;

use App\Mail\EnquiryActivity;
use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\EnquiryBlock;
use App\Models\EnquiryMessage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Attachments\AttachmentService;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The InfoDot Inbox (R-33 §5A). Every rule about enquiries lives here, so the controllers only
 * decide who is asking:
 *  - an enquiry belongs to the business; its owner and administrators handle every one, and a
 *    member handles the ones assigned to them;
 *  - the customer sees the conversation, never internal notes, events or the handler's name;
 *  - limits (per day, per business, duplicates, blocks) stop the inbox being used for spam;
 *  - each new message is one in-app notice per recipient and, at most once per half hour per
 *    conversation, a short email that says something arrived — never what it says.
 */
final class EnquiryService
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly InAppNotices $notices,
    ) {}

    /** Why this person cannot open a new enquiry with this business, or null when they can. */
    public function whyNot(User $user, Team $team): ?string
    {
        if (! $team->acceptsEnquiries()) {
            return $team->name.' is not taking messages through InfoDot right now.';
        }

        if ($user->belongsToTeam($team)) {
            return 'You belong to '.$team->name.', so its enquiries reach you in its inbox.';
        }

        if (EnquiryBlock::where('team_id', $team->id)->where('user_id', $user->id)->exists()) {
            return $team->name.' is not accepting new enquiries from your account.';
        }

        $since = now()->subDay();
        if (Enquiry::where('user_id', $user->id)->where('created_at', '>=', $since)->count() >= (int) config('enquiries.per_day')) {
            return 'You have opened '.config('enquiries.per_day').' enquiries in the last 24 hours, which is the most InfoDot allows. Try again tomorrow.';
        }

        if (Enquiry::where('user_id', $user->id)->where('team_id', $team->id)->where('created_at', '>=', $since)->count() >= (int) config('enquiries.per_business_per_day')) {
            return 'You have already opened '.config('enquiries.per_business_per_day').' enquiries with '.$team->name.' today. Add to one of them in your inbox instead.';
        }

        return null;
    }

    /**
     * Open an enquiry: the conversation, its first message and any files, then tell the business.
     *
     * @param  array{subject: string, category: string, body: string}  $data
     * @param  list<UploadedFile>  $files
     */
    public function start(User $customer, Team $team, array $data, array $files = []): Enquiry
    {
        if ($reason = $this->whyNot($customer, $team)) {
            throw ValidationException::withMessages(['body' => $reason]);
        }

        $this->guardDuplicate($customer, $data['body']);

        $enquiry = DB::transaction(function () use ($customer, $team, $data, $files) {
            $enquiry = new Enquiry(['subject' => $data['subject'], 'category' => $data['category']]);
            $enquiry->team_id = $team->id;
            $enquiry->user_id = $customer->id;
            $enquiry->status = Enquiry::NEW;
            $enquiry->last_customer_message_at = now();
            $enquiry->save();

            $this->write($enquiry, $customer, EnquiryMessage::CUSTOMER, $data['body'], $files);
            $this->markRead($enquiry, $customer);

            return $enquiry;
        });

        foreach ($this->recipientsFor($enquiry) as $handler) {
            $this->tell($handler, $enquiry, UserNotice::ENQUIRY_RECEIVED, 'new', $customer);
        }

        return $enquiry;
    }

    /**
     * Add to the conversation. The customer writes as themselves; a handler writes either to the
     * customer (as the business) or an internal note the customer never sees.
     *
     * @param  list<UploadedFile>  $files
     */
    public function reply(Enquiry $enquiry, User $author, string $body, array $files = [], bool $internal = false): EnquiryMessage
    {
        $asCustomer = $enquiry->isCustomer($author) && ! $enquiry->isHandler($author);

        if ($asCustomer && $enquiry->isClosed()) {
            throw ValidationException::withMessages(['body' => 'This enquiry is closed. Open a new one from the business page if you need more.']);
        }

        if ($asCustomer) {
            $this->guardDuplicate($author, $body);
        }

        $kind = $asCustomer ? EnquiryMessage::CUSTOMER : ($internal ? EnquiryMessage::NOTE : EnquiryMessage::BUSINESS);

        $message = DB::transaction(function () use ($enquiry, $author, $body, $files, $kind) {
            $message = $this->write($enquiry, $author, $kind, $body, $files);

            if ($kind === EnquiryMessage::CUSTOMER) {
                $enquiry->last_customer_message_at = now();
                // The customer wrote back: whatever was settled is open again.
                if (in_array($enquiry->status, [Enquiry::AWAITING, Enquiry::RESOLVED], true)) {
                    $enquiry->status = Enquiry::OPEN;
                }
            } elseif ($kind === EnquiryMessage::BUSINESS) {
                $enquiry->last_business_message_at = now();
                $enquiry->first_response_at ??= now();
                if (in_array($enquiry->status, [Enquiry::NEW, Enquiry::OPEN, Enquiry::IN_PROGRESS], true)) {
                    $enquiry->status = Enquiry::AWAITING;
                }
            }

            $enquiry->touch();
            $enquiry->save();
            $this->markRead($enquiry, $author);

            return $message;
        });

        $ref = 'msg:'.$message->id;
        if ($kind === EnquiryMessage::BUSINESS && $enquiry->customer !== null) {
            $this->tell($enquiry->customer, $enquiry, UserNotice::ENQUIRY_REPLY, $ref, $author);
        } elseif ($kind === EnquiryMessage::CUSTOMER) {
            foreach ($this->recipientsFor($enquiry) as $handler) {
                $this->tell($handler, $enquiry, UserNotice::ENQUIRY_REPLY, $ref, $author);
            }
        }

        return $message;
    }

    public function setStatus(Enquiry $enquiry, User $actor, string $status): void
    {
        if (! array_key_exists($status, Enquiry::STATUSES) || $status === $enquiry->status) {
            return;
        }

        DB::transaction(function () use ($enquiry, $actor, $status) {
            $from = Enquiry::STATUSES[$enquiry->status] ?? $enquiry->status;
            $enquiry->status = $status;
            $enquiry->closed_at = $status === Enquiry::CLOSED ? now() : null;
            $enquiry->save();
            $this->event($enquiry, $actor, 'Status: '.$from.' → '.Enquiry::STATUSES[$status]);
        });
    }

    /** Give the enquiry to one member of the business, or take it back to the whole inbox. */
    public function assign(Enquiry $enquiry, User $actor, ?User $assignee): void
    {
        if ($assignee !== null && ! $assignee->belongsToTeam($enquiry->team)) {
            throw ValidationException::withMessages(['assigned_to' => 'Only a member of '.$enquiry->team->name.' can take an enquiry.']);
        }

        if ($enquiry->assigned_to === $assignee?->id) {
            return;
        }

        DB::transaction(function () use ($enquiry, $actor, $assignee) {
            $enquiry->assigned_to = $assignee?->id;
            $enquiry->save();
            $this->event($enquiry, $actor, $assignee ? 'Assigned to '.$assignee->name : 'Returned to the whole inbox');
        });

        if ($assignee !== null) {
            $this->tell($assignee, $enquiry, UserNotice::ENQUIRY_ASSIGNED, 'assigned:'.now()->getTimestamp(), $actor);
        }
    }

    public function setFollowUp(Enquiry $enquiry, User $actor, ?Carbon $on): void
    {
        $enquiry->follow_up_on = $on;
        $enquiry->follow_up_notified_at = null;
        $enquiry->save();
        $this->event($enquiry, $actor, $on ? 'Follow up on '.$on->format('j M Y') : 'Follow-up date cleared');
    }

    public function markRead(Enquiry $enquiry, User $user): void
    {
        DB::table('enquiry_reads')->upsert(
            [['enquiry_id' => $enquiry->id, 'user_id' => $user->id, 'read_at' => now()]],
            ['enquiry_id', 'user_id'],
            ['read_at'],
        );
    }

    /** @param iterable<Enquiry> $enquiries @return array<int, Carbon> enquiry id => when this person last read it */
    public function readTimes(iterable $enquiries, User $user): array
    {
        $ids = collect($enquiries)->pluck('id')->all();

        return DB::table('enquiry_reads')->where('user_id', $user->id)->whereIn('enquiry_id', $ids)->pluck('read_at', 'enquiry_id')
            ->map(fn ($at) => Carbon::parse($at))->all();
    }

    public function block(Team $team, User $actor, User $customer): void
    {
        EnquiryBlock::firstOrCreate(['team_id' => $team->id, 'user_id' => $customer->id], ['blocked_by' => $actor->id]);
    }

    public function unblock(Team $team, User $customer): void
    {
        EnquiryBlock::where('team_id', $team->id)->where('user_id', $customer->id)->delete();
    }

    /** The people who answer for a business: its owner and administrators. @return Collection<int, User> */
    public function handlers(Team $team): Collection
    {
        $admins = $team->users()->wherePivot('role', 'admin')->get();

        return collect([$team->owner])->filter()->concat($admins)->unique('id')->values();
    }

    /** Who is told about a customer's message: the assignee when there is one, else every handler. @return Collection<int, User> */
    public function recipientsFor(Enquiry $enquiry): Collection
    {
        $assignee = $enquiry->assignee;

        return $assignee !== null && $assignee->belongsToTeam($enquiry->team) ? collect([$assignee]) : $this->handlers($enquiry->team);
    }

    /**
     * Enquiries this person can handle: every one of the businesses they own or administer, plus those assigned to them.
     *
     * @return Builder<Enquiry>
     */
    public function handledQuery(User $user): Builder
    {
        $teamIds = $user->allTeams()->filter(fn (Team $t) => ! $t->personal_team && ($user->ownsTeam($t) || $user->hasTeamRole($t, 'admin')))->pluck('id');

        return Enquiry::query()->where(fn ($q) => $q->whereIn('team_id', $teamIds)->orWhere('assigned_to', $user->id));
    }

    /** Conversations with something this person has not opened: as a customer, and as a handler. */
    public function unreadCount(User $user): int
    {
        $reads = DB::table('enquiry_reads')->select('enquiry_id', 'read_at')->where('user_id', $user->id);

        $asCustomer = Enquiry::query()->where('enquiries.user_id', $user->id)
            ->leftJoinSub($reads, 'r', 'r.enquiry_id', '=', 'enquiries.id')
            ->whereNotNull('last_business_message_at')
            ->where(fn ($q) => $q->whereNull('r.read_at')->orWhereColumn('last_business_message_at', '>', 'r.read_at'))
            ->count();

        $asHandler = $this->handledQuery($user)
            ->leftJoinSub($reads, 'r', 'r.enquiry_id', '=', 'enquiries.id')
            ->whereNotIn('status', [Enquiry::CLOSED])
            ->where(fn ($q) => $q->whereNull('r.read_at')->orWhereColumn('last_customer_message_at', '>', 'r.read_at'))
            ->count();

        return $asCustomer + $asHandler;
    }

    /**
     * Plain numbers for the business inbox, from what was recorded — nothing estimated.
     *
     * @return array{by_status: array<string, int>, last_30_days: int, answered: int, median_first_response_hours: ?float}
     */
    public function stats(Team $team): array
    {
        $byStatus = $team->enquiries()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();

        $recent = $team->enquiries()->where('created_at', '>=', now()->subDays(90))->get(['created_at', 'first_response_at']);
        $hours = $recent->filter(fn (Enquiry $e) => $e->first_response_at !== null)
            ->map(fn (Enquiry $e) => $e->created_at->diffInMinutes($e->first_response_at) / 60)->sort()->values();

        $median = null;
        if ($hours->isNotEmpty()) {
            $mid = intdiv($hours->count(), 2);
            $median = round($hours->count() % 2 ? $hours[$mid] : ($hours[$mid - 1] + $hours[$mid]) / 2, 1);
        }

        return [
            'by_status' => $byStatus,
            'last_30_days' => $team->enquiries()->where('created_at', '>=', now()->subDays(30))->count(),
            'answered' => $hours->count(),
            'median_first_response_hours' => $median,
        ];
    }

    /** Enquiries whose follow-up date has come: one notice to whoever has it. @return int notices sent */
    public function sendFollowUps(): int
    {
        $sent = 0;

        Enquiry::query()->active()->whereNotNull('follow_up_on')->whereDate('follow_up_on', '<=', now()->toDateString())
            ->whereNull('follow_up_notified_at')->with(['team', 'assignee'])->chunkById(100, function ($due) use (&$sent) {
                foreach ($due as $enquiry) {
                    if ($enquiry->team === null) {
                        continue;
                    }
                    foreach ($this->recipientsFor($enquiry) as $user) {
                        $sent += (int) $this->notices->record(UserNotice::ENQUIRY_FOLLOW_UP, $user, 'enquiry', $enquiry->id, 'enq:'.$enquiry->id.':follow:'.$enquiry->follow_up_on->toDateString());
                    }
                    $enquiry->forceFill(['follow_up_notified_at' => now()])->saveQuietly();
                }
            });

        return $sent;
    }

    // ------------------------------------------------------------------ internals

    /** @param list<UploadedFile> $files */
    private function write(Enquiry $enquiry, User $author, string $kind, string $body, array $files): EnquiryMessage
    {
        if (count($files) > (int) config('enquiries.max_files')) {
            throw ValidationException::withMessages(['files' => 'Attach at most '.config('enquiries.max_files').' files to one message.']);
        }

        $message = EnquiryMessage::create([
            'enquiry_id' => $enquiry->id,
            'user_id' => $author->id,
            'kind' => $kind,
            'body' => trim($body),
            'body_hash' => hash('sha256', $this->normalise($body)),
            'created_at' => now(),
        ]);

        // Files go through the same checks as every InfoDot upload (type sniffing, size, daily quota),
        // then belong to this message: only the people in the conversation may open them.
        foreach ($files as $i => $file) {
            $attachment = $this->attachments->upload($author, $file);
            $attachment->forceFill([
                'attachable_type' => $message->getMorphClass(),
                'attachable_id' => $message->id,
                'team_id' => $enquiry->team_id,
                'sort_order' => $i,
                'expires_at' => null,
            ])->save();
        }

        return $message;
    }

    private function event(Enquiry $enquiry, User $actor, string $text): void
    {
        EnquiryMessage::create(['enquiry_id' => $enquiry->id, 'user_id' => $actor->id, 'kind' => EnquiryMessage::EVENT, 'body' => $text, 'created_at' => now()]);
    }

    private function guardDuplicate(User $user, string $body): void
    {
        $hash = hash('sha256', $this->normalise($body));
        $dupe = EnquiryMessage::where('user_id', $user->id)->where('body_hash', $hash)
            ->where('created_at', '>=', now()->subHours((int) config('enquiries.duplicate_hours')))->exists();

        if ($dupe) {
            throw ValidationException::withMessages(['body' => 'You sent exactly these words in the last day. Check your inbox; the business has them.']);
        }
    }

    private function normalise(string $body): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $body)));
    }

    /** One in-app notice, and an email unless one went to this person about this conversation lately. */
    private function tell(User $recipient, Enquiry $enquiry, string $kind, string $ref, ?User $actor): void
    {
        if ($actor !== null && $actor->is($recipient)) {
            return;
        }

        $this->notices->record($kind, $recipient, 'enquiry', $enquiry->id, 'enq:'.$enquiry->id.':'.$ref, $actor);

        if (! Cache::add('enquiry-mail:'.$enquiry->id.':'.$recipient->id, 1, now()->addMinutes((int) config('enquiries.email_quiet_minutes')))) {
            return;
        }

        try {
            Mail::to($recipient)->send(new EnquiryActivity($kind, $enquiry, $recipient, $enquiry->isCustomer($recipient)));
        } catch (\Throwable $e) {
            // The message is safely in the inbox and the bell; a mail outage must not fail the conversation.
            report($e);
        }
    }

    /** Files on the messages this person may see, keyed by message id. @param Collection<int, EnquiryMessage> $messages @return Collection<int, Collection<int, Attachment>> */
    public function filesFor(Collection $messages): Collection
    {
        return Attachment::query()->where('attachable_type', 'enquiry_message')->whereIn('attachable_id', $messages->pluck('id'))
            ->orderBy('sort_order')->get()->groupBy('attachable_id');
    }
}
