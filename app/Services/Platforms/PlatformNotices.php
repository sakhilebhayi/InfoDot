<?php

namespace App\Services\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlementEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells a company's owner and administrators, in InfoDot, when the company's entitlement to a platform changes
 * (R-50 section 6). It listens for a new row in the entitlement history, so every writer is covered: a company
 * switching a platform on or off, and a platform administrator granting, suspending or restoring one. The person who
 * made the change is not told about their own act. Choosing which individual people may open a platform is not an
 * entitlement change and tells nobody.
 *
 * A notice holds no words. The page writes them from the reference when it is read.
 */
class PlatformNotices
{
    /** History entries that are about one person's permission, not about what the company holds. */
    public const QUIET = ['user_granted', 'user_revoked'];

    public static function register(): void
    {
        PlatformEntitlementEvent::created(function (PlatformEntitlementEvent $event): void {
            if (in_array($event->type, self::QUIET, true)) {
                return;
            }

            // After the change is really saved, and never able to undo it: a notice that fails is logged, not thrown.
            DB::afterCommit(function () use ($event): void {
                try {
                    app(self::class)->tell($event);
                } catch (Throwable $e) {
                    Log::warning('Platform entitlement notice was not recorded.', ['event' => $event->id, 'error' => $e->getMessage()]);
                }
            });
        });
    }

    public function __construct(private readonly InAppNotices $notices) {}

    /** @return int how many people were told */
    public function tell(PlatformEntitlementEvent $event): int
    {
        $team = Team::find($event->team_id);

        if ($team === null || $team->personal_team) {
            return 0;
        }

        $actor = $event->actor_id !== null ? User::find($event->actor_id) : null;
        $told = 0;

        foreach ($this->recipients($team) as $person) {
            $told += (int) $this->notices->record(
                UserNotice::PLATFORM_ENTITLEMENT, $person, 'business', (int) $team->id,
                "pe:{$event->id}:{$event->platform_key}:{$event->type}", $actor,
            );
        }

        return $told;
    }

    /**
     * The owner and the administrators of a company.
     *
     * @return list<User>
     */
    public function recipients(Team $team): array
    {
        $ids = DB::table('team_user')->where('team_id', $team->id)->where('role', TeamRoles::ADMIN)->pluck('user_id')->push($team->user_id)->unique();

        // The owner first, then the administrators.
        return User::whereIn('id', $ids)->get()->sortBy(fn (User $person) => $person->id === $team->user_id ? 0 : 1)->values()->all();
    }

    /**
     * The words for one notice, from its reference.
     *
     * @return array{text: string, url: ?string}
     */
    public static function describe(string $ref, Team $team, string $company): array
    {
        [, , $key, $type] = array_pad(explode(':', $ref), 4, null);
        $platform = (string) (PlatformCatalogue::where('key', (string) $key)->value('name') ?? 'A platform');

        $text = match ($type) {
            'activated' => "{$platform} was switched on for {$company}. People in the company can open it now.",
            'deactivated' => "{$platform} was switched off for {$company}. Nothing was deleted, and it can be switched on again.",
            'complimentary_granted' => "{$platform} was given to {$company} at no charge by InfoDot.",
            'suspended' => "An InfoDot administrator paused {$company}'s use of {$platform}. Nothing was deleted.",
            'restored' => "{$company}'s use of {$platform} was restored.",
            'access_mode_changed' => "Who may open {$platform} in {$company} was changed.",
            default => "{$company}'s access to {$platform} changed.",
        };

        return ['text' => $text, 'url' => $key !== null ? route('platforms.show', ['platform' => $key]) : null];
    }
}
