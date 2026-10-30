<?php

namespace App\Services\Seminars;

use App\Models\Seminar;
use App\Models\SeminarFeedback;
use App\Models\SeminarRegistration;
use App\Services\Points\RewardOutbox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Dot Points for hosting a seminar. A host earns once, when a seminar they ran finished well attended and well rated by people
 * who are not their colleagues; attending earns nothing. The event goes through the same durable outbox, signing and idempotent
 * event ids as every other InfoDot contribution event (RewardOutbox, PointsClient); the ledger decides what it is worth and applies
 * the same thresholds again. Nothing but three counts leaves InfoDot: no attendee, no rating, no name.
 *
 * "Outside": an attendee who is a member or owner of any company (team) the host belongs to or owns does not count, and neither does
 * their rating. The rule is on the people, not on the seminar's visibility.
 */
class SeminarHostPoints
{
    public const TYPE = 'infodot.seminar.hosted';

    public const REVERSAL_TYPE = 'infodot.contribution.reward_reversed';

    public function __construct(private readonly RewardOutbox $outbox) {}

    public function enabled(): bool
    {
        return (bool) config('seminars.points.enabled');
    }

    public static function refFor(Seminar $seminar): string
    {
        return 'seminar:'.$seminar->id;
    }

    /**
     * What counts for this seminar right now: attendees and ratings from outside the host's companies.
     *
     * @return array{attendees: int, ratings: int, average: ?float, colleagues: int} `average` is unrounded; `colleagues` is only how many attendees were left out
     */
    public function figures(Seminar $seminar): array
    {
        $attended = SeminarRegistration::where('seminar_id', $seminar->id)->whereNotNull('attended_at')->where('user_id', '!=', $seminar->host_id)->pluck('user_id')->unique();
        $mates = $this->colleagueIds($seminar->host_id);
        $outside = $attended->reject(fn ($id) => $mates->contains($id))->values();
        $ratings = $outside->isEmpty() ? collect() : SeminarFeedback::where('seminar_id', $seminar->id)->whereIn('user_id', $outside->all())->pluck('rating');

        return [
            'attendees' => $outside->count(), 'ratings' => $ratings->count(),
            'average' => $ratings->isEmpty() ? null : (float) $ratings->avg(),
            'colleagues' => $attended->count() - $outside->count(),
        ];
    }

    /** @param array{attendees: int, ratings: int, average: ?float} $f */
    public function meetsThresholds(array $f): bool
    {
        return $f['attendees'] >= (int) config('seminars.points.min_attendees', 3)
            && $f['ratings'] >= (int) config('seminars.points.min_ratings', 3)
            && $f['average'] !== null && $f['average'] >= (float) config('seminars.points.min_average', 4.0);
    }

    /** True once the wait for late ratings is over: the seminar ended at least `wait_hours` ago. */
    public function waitIsOver(Seminar $seminar): bool
    {
        return $seminar->endsAt()->addHours((int) config('seminars.points.wait_hours', 24))->lte(now());
    }

    /**
     * Queues the award for every seminar that is due, once each, and takes back awards of seminars cancelled since.
     *
     * @return array{awarded: int, reversed: int, checked: int}
     */
    public function run(): array
    {
        $result = ['awarded' => 0, 'reversed' => 0, 'checked' => 0];

        if (! $this->enabled()) {
            return $result;
        }

        foreach (Seminar::where('status', Seminar::CANCELLED)->whereNotNull('host_points_event_id')->whereNull('host_points_reversed_at')->get() as $cancelled) {
            $this->reverseIfAwarded($cancelled) && $result['reversed']++;
        }

        $days = max(1, (int) config('seminars.points.consider_days', 30));
        $due = Seminar::where('status', Seminar::PUBLISHED)->whereNull('host_points_event_id')
            ->where('starts_at', '<=', now()->subHours((int) config('seminars.points.wait_hours', 24)))
            ->where('starts_at', '>=', now()->subDays($days + 1)->subMinutes((int) config('seminars.max_duration_minutes', 480)))
            ->orderBy('id')->get()
            ->filter(fn (Seminar $s) => $this->waitIsOver($s) && $s->endsAt()->gte(now()->subDays($days)));

        foreach ($due as $seminar) {
            $result['checked']++;
            $this->award($seminar) && $result['awarded']++;
        }

        return $result;
    }

    /** Queues the event if (and only if) the seminar qualifies and no award was queued before. @return bool whether an event was queued now */
    public function award(Seminar $seminar): bool
    {
        if (! $this->enabled() || ! $seminar->isPublished() || ! $this->waitIsOver($seminar) || $seminar->host_points_event_id !== null) {
            return false;
        }

        $f = $this->figures($seminar);

        if (! $this->meetsThresholds($f)) {
            return false;
        }

        $eventId = $this->eventId(self::TYPE, $seminar, 'awarded');
        $host = $seminar->host;

        return DB::transaction(function () use ($seminar, $f, $eventId, $host): bool {
            // The claim is a conditional update, so two overlapping runs cannot both queue it; the event id is derived from the seminar, so a replay is a duplicate to the ledger anyway.
            $claimed = Seminar::whereKey($seminar->id)->where('status', Seminar::PUBLISHED)->whereNull('host_points_event_id')
                ->update(['host_points_event_id' => $eventId, 'host_points_queued_at' => now()]);

            if ($claimed === 0) {
                return false;
            }

            $this->outbox->enqueue($this->envelope(self::TYPE, $eventId, $seminar, $host->uuid, [
                'attendee_count' => $f['attendees'],
                'rating_count' => $f['ratings'],
                'average_rating' => round($f['average'], 1),
            ]));

            return true;
        });
    }

    /** If an award was queued and not yet taken back, queues the existing reversal event for it. @return bool whether a reversal was queued now */
    public function reverseIfAwarded(Seminar $seminar): bool
    {
        $seminar->refresh();

        if ($seminar->host_points_event_id === null || $seminar->host_points_reversed_at !== null || $seminar->status !== Seminar::CANCELLED) {
            return false;
        }

        return DB::transaction(function () use ($seminar): bool {
            $claimed = Seminar::whereKey($seminar->id)->whereNull('host_points_reversed_at')->update(['host_points_reversed_at' => now()]);

            if ($claimed === 0) {
                return false;
            }

            $this->outbox->enqueue($this->envelope(self::REVERSAL_TYPE, $this->eventId(self::REVERSAL_TYPE, $seminar, 'cancelled'), $seminar, $seminar->host->uuid, [
                'rule_key' => 'seminar_hosted',
                'reason' => 'The seminar was cancelled.',
            ]));

            return true;
        });
    }

    /**
     * One plain sentence for the host about whether this seminar qualifies, or why not yet. Counts only: never who attended or rated.
     * Null when points for hosting are switched off or there is nothing to say (a draft).
     */
    public function sentence(Seminar $seminar): ?string
    {
        if (! $this->enabled() || $seminar->status === Seminar::DRAFT) {
            return null;
        }

        $min = (int) config('seminars.points.min_attendees', 3);
        $rate = (int) config('seminars.points.min_ratings', 3);
        $avg = number_format((float) config('seminars.points.min_average', 4.0), 1);
        $need = "To earn Dot Points for hosting, at least {$min} people who are not in your companies must attend, and at least {$rate} of them must rate it, averaging {$avg} or more.";

        if ($seminar->status === Seminar::CANCELLED) {
            return 'Cancelled seminars do not earn Dot Points for hosting.';
        }

        if (! $seminar->hasEnded()) {
            return $need.' We count after it has finished and the ratings have had '.config('seminars.points.wait_hours', 24).' hours to arrive.';
        }

        $f = $this->figures($seminar);
        $counts = sprintf('%d %s from outside your companies attended and %d of them rated it%s.', $f['attendees'], Str::plural('person', $f['attendees']), $f['ratings'], $f['average'] !== null ? ', averaging '.number_format($f['average'], 1) : '');
        $left = $f['colleagues'] > 0 ? sprintf(' %d %s from your own companies %s not counted.', $f['colleagues'], Str::plural('attendee', $f['colleagues']), $f['colleagues'] === 1 ? 'is' : 'are') : '';

        if ($seminar->host_points_event_id !== null) {
            return 'This seminar qualified: '.$counts.' Your Dot Points for hosting it were sent.'.$left;
        }

        if (! $this->waitIsOver($seminar)) {
            $when = $seminar->endsAt()->addHours((int) config('seminars.points.wait_hours', 24))->timezone(config('app.timezone'))->format('j M, H:i');

            return 'Not counted yet: ratings stay open until '.$when.'. So far, '.$counts.($this->meetsThresholds($f) ? ' That would qualify.' : ' '.$need).$left;
        }

        return $this->meetsThresholds($f)
            ? 'This seminar qualifies: '.$counts.' Your Dot Points for hosting it will be sent shortly.'.$left
            : 'This seminar did not qualify: '.$counts.' '.$need.$left;
    }

    /** People who share a company with the host: members and owners of every team the host belongs to or owns. @return Collection<int, int> */
    private function colleagueIds(int $hostId): Collection
    {
        $teams = DB::table('team_user')->where('user_id', $hostId)->pluck('team_id')->merge(DB::table('teams')->where('user_id', $hostId)->pluck('id'))->unique();

        if ($teams->isEmpty()) {
            return collect();
        }

        return DB::table('team_user')->whereIn('team_id', $teams)->pluck('user_id')
            ->merge(DB::table('teams')->whereIn('id', $teams)->pluck('user_id'))->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => $id === $hostId)->values();
    }

    /** The same derivation the other InfoDot events use for a keyed action: a replay of the action can never make a second ledger event. */
    private function eventId(string $type, Seminar $seminar, string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'infodot:'.$type.':'.self::refFor($seminar).':'.$key)->toString();
    }

    /**
     * The envelope the outbox and PointsClient already carry for every other event, with exactly these keys and no others.
     *
     * @param  array<string, int|float|string>  $facts
     * @return array<string, mixed>
     */
    private function envelope(string $type, string $eventId, Seminar $seminar, string $hostUuid, array $facts): array
    {
        return [
            'event_id' => $eventId,
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'user_uuid' => $hostUuid,
            'contribution_ref' => self::refFor($seminar),
            'visibility' => 'public',
            'facts' => $facts,
        ];
    }
}
