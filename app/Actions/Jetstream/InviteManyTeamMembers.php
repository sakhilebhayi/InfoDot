<?php

namespace App\Actions\Jetstream;

use App\Services\Licensing\SeatLimitReached;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invites several people at once (R-62 phase 4). Every address goes through the same single-invitation action, so the permission check,
 * role cap, deliverable-domain check, seat guard, mail failure handling and audit all apply to each one. The result says, per address,
 * what happened; one bad address never stops the others, but once the company is full the rest are not tried.
 */
class InviteManyTeamMembers
{
    public const MAX = 25;

    /** @return list<string> the distinct, lower-cased addresses found in free text (commas, semicolons, spaces and new lines all separate) */
    public static function parse(string $text): array
    {
        $found = [];
        foreach (preg_split('/[\s,;]+/', Str::lower(trim(mb_substr($text, 0, 5000))), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            $piece = trim($piece, "<>()\"'");
            $found[$piece] = true;
        }

        return array_keys($found);
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $team
     * @return array{invited: list<string>, skipped: list<array{email: string, reason: string}>, too_many: bool}
     */
    public function invite($user, $team, string $text, ?string $role): array
    {
        $emails = self::parse($text);
        $tooMany = count($emails) > self::MAX;
        $emails = array_slice($emails, 0, self::MAX);

        $invited = [];
        $skipped = [];
        $full = false;

        foreach ($emails as $email) {
            if ($full) {
                $skipped[] = ['email' => $email, 'reason' => __('Not tried: the company has no licence free.')];

                continue;
            }

            try {
                app(InviteTeamMember::class)->invite($user, $team, $email, $role);
                $invited[] = $email;
            } catch (SeatLimitReached $e) {
                $full = true;
                $skipped[] = ['email' => $email, 'reason' => collect($e->errors())->flatten()->first() ?? __('The company has no licence free.')];
            } catch (ValidationException $e) {
                $skipped[] = ['email' => $email, 'reason' => (string) (collect($e->errors())->flatten()->first() ?? __('Could not be invited.'))];
            }
        }

        return ['invited' => $invited, 'skipped' => $skipped, 'too_many' => $tooMany];
    }
}
