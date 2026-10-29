<?php

namespace App\Services\Seminars;

use App\Models\Seminar;
use App\Models\SeminarFeedback;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Free seminars: hosting, registering, joining, attending, rating. Rules in one place:
 *  - any verified person may host; no tier, reputation or seat is needed;
 *  - a host can have only a few upcoming published seminars (config), so the list cannot be flooded;
 *  - the join link is the host's own and is handed out only to registered people inside the window; opening it records attendance;
 *  - the last seat is taken under a row lock, so two people cannot both get it; the rest wait in order and are moved up when a place opens;
 *  - only people who attended can rate, once; the comment is for the host, and a host's average is shown publicly only from a few ratings.
 */
class SeminarService
{
    public function __construct(private readonly InAppNotices $notices) {}

    /** @return array<string, mixed> validation rules for the host's form */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:140'],
            'summary' => ['required', 'string', 'max:280'],
            'description' => ['nullable', 'string', 'max:5000'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:'.config('seminars.min_duration_minutes', 15), 'max:'.config('seminars.max_duration_minutes', 480)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:'.config('seminars.max_capacity', 5000)],
            'join_url' => ['required', 'url:https', 'max:500'],
        ];
    }

    /** @param  array<string, mixed>  $data  validated by rules() */
    public function save(User $host, ?Seminar $seminar, array $data, bool $publish): Seminar
    {
        $starts = \Illuminate\Support\Carbon::parse($data['starts_at'], config('app.timezone'))->utc();
        $this->assertLead($starts);

        return DB::transaction(function () use ($host, $seminar, $data, $publish, $starts) {
            User::whereKey($host->getKey())->lockForUpdate()->first();

            if ($seminar !== null && $seminar->status === Seminar::CANCELLED) {
                throw ValidationException::withMessages(['title' => 'A cancelled seminar cannot be edited.']);
            }

            $willBePublished = $publish || ($seminar?->isPublished() ?? false);

            if ($willBePublished && ! ($seminar?->isPublished() ?? false)) {
                $this->assertRoomToPublish($host);
            }

            $before = $seminar?->starts_at;
            $attrs = [
                'title' => $data['title'], 'summary' => $data['summary'], 'description' => $data['description'] ?? null,
                'topic_id' => $data['topic_id'] ?? null, 'starts_at' => $starts, 'duration_minutes' => (int) $data['duration_minutes'],
                'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null, 'join_url' => $data['join_url'],
                'status' => $willBePublished ? Seminar::PUBLISHED : Seminar::DRAFT,
            ];

            $seminar = $seminar === null ? Seminar::create($attrs + ['host_id' => $host->id]) : tap($seminar)->update($attrs);

            if ($before !== null && ! $before->equalTo($starts) && $seminar->isPublished()) {
                $this->tell(UserNotice::SEMINAR_CHANGED, $seminar, 'changed:'.$starts->timestamp);
            }

            return $seminar;
        });
    }

    public function cancel(Seminar $seminar): void
    {
        if ($seminar->status === Seminar::CANCELLED) {
            return;
        }

        $wasPublished = $seminar->isPublished();
        $seminar->forceFill(['status' => Seminar::CANCELLED, 'cancelled_at' => now()])->save();

        if ($wasPublished) {
            $this->tell(UserNotice::SEMINAR_CANCELLED, $seminar, 'cancelled');
        }
    }

    /** @return string registered|waitlist */
    public function register(Seminar $seminar, User $user): string
    {
        if ($seminar->host_id === $user->id) {
            throw ValidationException::withMessages(['seminar' => 'You are hosting this seminar.']);
        }

        return DB::transaction(function () use ($seminar, $user) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPublished() || $locked->hasEnded()) {
                throw ValidationException::withMessages(['seminar' => 'This seminar is not open for registration.']);
            }

            $existing = SeminarRegistration::where('seminar_id', $locked->id)->where('user_id', $user->id)->first();

            if ($existing && $existing->status !== SeminarRegistration::CANCELLED) {
                return $existing->status;
            }

            $taken = SeminarRegistration::where('seminar_id', $locked->id)->where('status', SeminarRegistration::REGISTERED)->count();
            $status = $locked->capacity !== null && $taken >= $locked->capacity ? SeminarRegistration::WAITLIST : SeminarRegistration::REGISTERED;

            SeminarRegistration::updateOrCreate(['seminar_id' => $locked->id, 'user_id' => $user->id], ['status' => $status, 'attended_at' => null]);

            return $status;
        });
    }

    public function unregister(Seminar $seminar, User $user): void
    {
        DB::transaction(function () use ($seminar, $user) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();
            $reg = SeminarRegistration::where('seminar_id', $locked->id)->where('user_id', $user->id)->first();

            if (! $reg || $reg->status === SeminarRegistration::CANCELLED) {
                return;
            }

            $held = $reg->status === SeminarRegistration::REGISTERED;
            $reg->forceFill(['status' => SeminarRegistration::CANCELLED])->save();

            if ($held && $locked->isPublished() && ! $locked->hasEnded()) {
                $next = SeminarRegistration::where('seminar_id', $locked->id)->where('status', SeminarRegistration::WAITLIST)->orderBy('id')->first();

                if ($next) {
                    $next->forceFill(['status' => SeminarRegistration::REGISTERED])->save();
                    $this->notices->record(UserNotice::SEMINAR_SPOT_OPEN, User::findOrFail($next->user_id), 'seminar', $locked->id, 'seminar:'.$locked->id.':spot');
                }
            }
        });
    }

    /** The link to open, or null when this person may not join now. Opening it is what records attendance. */
    public function joinUrl(Seminar $seminar, User $user): ?string
    {
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->where('status', SeminarRegistration::REGISTERED)->first();

        if ($seminar->host_id === $user->id) {
            return $seminar->isPublished() ? $seminar->join_url : null;
        }

        if (! $reg || ! $seminar->joinIsOpen()) {
            return null;
        }

        if ($reg->attended_at === null) {
            $reg->forceFill(['attended_at' => now()])->save();
        }

        return $seminar->join_url;
    }

    public function setAttended(Seminar $seminar, User $person, bool $attended): void
    {
        SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $person->id)->where('status', SeminarRegistration::REGISTERED)
            ->update(['attended_at' => $attended ? now() : null]);
    }

    public function rate(Seminar $seminar, User $user, int $rating, ?string $comment): void
    {
        $attended = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->whereNotNull('attended_at')->exists();

        if (! $seminar->isPublished() || ! $seminar->hasEnded() || ! $attended || $seminar->host_id === $user->id) {
            throw ValidationException::withMessages(['rating' => 'Only people who attended can rate a seminar, once it has finished.']);
        }

        SeminarFeedback::updateOrCreate(['seminar_id' => $seminar->id, 'user_id' => $user->id], ['rating' => max(1, min(5, $rating)), 'comment' => $comment !== null && trim($comment) !== '' ? mb_substr(trim($comment), 0, 1000) : null]);
    }

    /** @return array{hosted: int, attendees: int, ratings: int, average: ?float} average is null until enough people have rated */
    public function hostStats(User $host): array
    {
        $ended = Seminar::where('host_id', $host->id)->where('status', Seminar::PUBLISHED)->get()->filter->hasEnded();
        $ids = $ended->pluck('id');
        $ratings = SeminarFeedback::whereIn('seminar_id', $ids)->pluck('rating');

        return [
            'hosted' => $ended->count(),
            'attendees' => SeminarRegistration::whereIn('seminar_id', $ids)->whereNotNull('attended_at')->count(),
            'ratings' => $ratings->count(),
            'average' => $ratings->count() >= (int) config('seminars.min_ratings_shown', 3) ? round($ratings->avg(), 1) : null,
        ];
    }

    /** Day-before and hour-before reminders to people who registered. Safe to run often: one notice per person, seminar and kind of reminder. */
    public function sendReminders(): int
    {
        $sent = 0;

        foreach (Seminar::where('status', Seminar::PUBLISHED)->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDay())->get() as $seminar) {
            $window = $seminar->starts_at->lte(now()->addHour()) ? 'hour' : 'day';

            foreach (SeminarRegistration::where('seminar_id', $seminar->id)->where('status', SeminarRegistration::REGISTERED)->get() as $reg) {
                $sent += (int) $this->notices->record(UserNotice::SEMINAR_REMINDER, User::findOrFail($reg->user_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':'.$window);
            }
        }

        return $sent;
    }

    private function tell(string $kind, Seminar $seminar, string $suffix): void
    {
        $host = User::find($seminar->host_id);

        foreach (SeminarRegistration::where('seminar_id', $seminar->id)->whereIn('status', [SeminarRegistration::REGISTERED, SeminarRegistration::WAITLIST])->pluck('user_id') as $uid) {
            $this->notices->record($kind, User::findOrFail($uid), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':'.$suffix, $host);
        }
    }

    private function assertLead(\Illuminate\Support\Carbon $starts): void
    {
        if ($starts->lt(now()->addMinutes((int) config('seminars.min_lead_minutes', 60)))) {
            throw ValidationException::withMessages(['starts_at' => 'A seminar must start at least '.config('seminars.min_lead_minutes', 60).' minutes from now.']);
        }
    }

    private function assertRoomToPublish(User $host): void
    {
        $upcoming = Seminar::where('host_id', $host->id)->where('status', Seminar::PUBLISHED)->get()->reject->hasEnded()->count();
        $max = (int) config('seminars.max_upcoming_per_host', 5);

        if ($upcoming >= $max) {
            throw ValidationException::withMessages(['title' => "You already have $max upcoming seminars. Wait for one to finish or cancel one first."]);
        }
    }
}
