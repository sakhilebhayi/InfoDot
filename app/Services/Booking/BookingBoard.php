<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingClosure;
use App\Models\BookingProvider;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Which bookings a person sees on the appointments screens (R-63 phase 4). Every list, the calendar and every count asks this class, so the rule
 * lives in one place and a screen cannot forget it: the owner, administrators and managers see every booking of the business (and may narrow them to
 * one provider or to the unassigned ones); a member who is a provider sees only the bookings assigned to them, with no filter; anyone else sees none.
 */
class BookingBoard
{
    public const UNASSIGNED = 'unassigned';

    /** @var list<int> the provider records of this person in this business (empty for a manager who is not one) */
    private array $ownProviderIds;

    private readonly bool $manages;

    private ?string $filter = null;

    private function __construct(private readonly User $user, private readonly Team $team, ?string $filter)
    {
        $this->manages = Gate::forUser($user)->allows('manageBookings', $team);
        // Only while they are still in the business and the provider record is active.
        $this->ownProviderIds = $user->belongsToTeam($team)
            ? BookingProvider::query()->where('team_id', $team->getKey())->where('user_id', $user->getKey())->where('active', true)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        if ($this->manages && $filter !== null) {
            if ($filter === self::UNASSIGNED) {
                $this->filter = self::UNASSIGNED;
            } elseif (ctype_digit($filter) && BookingProvider::query()->where('team_id', $team->getKey())->whereKey((int) $filter)->exists()) {
                $this->filter = $filter;
            }
        }
    }

    public static function for(User $user, Team $team, ?string $providerFilter = null): self
    {
        return new self($user, $team, $providerFilter);
    }

    /** Whether this person has any business on these screens at all. */
    public function canUse(): bool
    {
        return ! $this->team->personal_team && ($this->manages || $this->ownProviderIds !== []);
    }

    public function manages(): bool
    {
        return $this->manages;
    }

    /** The provider filter in force: a provider id as text, 'unassigned', or null. Only ever set for people who manage the business. */
    public function filter(): ?string
    {
        return $this->filter;
    }

    /**
     * The providers whose offered time this person's view covers: null for a manager looking at everything (the business and every provider), the one chosen
     * provider, none for "not assigned", or the person's own provider records. Used for the capacity side of utilisation.
     *
     * @return list<int>|null
     */
    public function capacityProviderIds(): ?array
    {
        if (! $this->manages) {
            return $this->ownProviderIds;
        }

        return match (true) {
            $this->filter === null => null,
            $this->filter === self::UNASSIGNED => [],
            default => [(int) $this->filter],
        };
    }

    /** @return Builder<Booking> */
    public function query(): Builder
    {
        $query = Booking::query()->where('team_id', $this->team->getKey());

        return $this->scope($query, true);
    }

    /** Pending requests still ahead that this person may act on, whatever the filter. */
    public function pendingCount(): int
    {
        return $this->scope(Booking::query()->where('team_id', $this->team->getKey()), false)
            ->where('status', Booking::PENDING)->where('starts_at', '>', now())->count();
    }

    /**
     * Closures that explain an empty stretch of the calendar: the business's own, and those of the providers this person is looking at.
     *
     * @return Collection<int, BookingClosure>
     */
    public function closures(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $query = BookingClosure::query()->where('team_id', $this->team->getKey())->where('starts_at', '<', $to)->where('ends_at', '>', $from)->with('provider:id,public_name');

        if (! $this->manages) {
            $query->where(fn ($q) => $q->whereNull('booking_provider_id')->orWhereIn('booking_provider_id', $this->ownProviderIds));
        } elseif ($this->filter === self::UNASSIGNED) {
            $query->whereNull('booking_provider_id');
        } elseif ($this->filter !== null) {
            $query->where(fn ($q) => $q->whereNull('booking_provider_id')->orWhere('booking_provider_id', (int) $this->filter));
        }

        return $query->orderBy('starts_at')->get();
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private function scope(Builder $query, bool $withFilter): Builder
    {
        if (! $this->manages) {
            return $this->ownProviderIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('booking_provider_id', $this->ownProviderIds);
        }

        if ($withFilter && $this->filter === self::UNASSIGNED) {
            return $query->whereNull('booking_provider_id');
        }

        return $withFilter && $this->filter !== null ? $query->where('booking_provider_id', (int) $this->filter) : $query;
    }
}
