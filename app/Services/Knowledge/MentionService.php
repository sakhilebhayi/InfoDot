<?php

namespace App\Services\Knowledge;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Mention;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Who may be mentioned, how a mention is recorded, and who gets told (R-16 phase 2). People are searchable by name —
 * their profiles and public contributions are already platform-wide. A business is searchable only once it has public
 * content of its own, so one company's private client list never surfaces in another company's autocomplete. A
 * mentioned business notifies its owner and admins (its authorised representatives), never every member.
 */
class MentionService
{
    public const MAX_PER_ITEM = 5;

    public const PER_HOUR = 20;

    /**
     * Autocomplete behind the @ box. Names only — never addresses or private detail.
     *
     * @return list<array{type: string, id: int, name: string, detail: string}>
     */
    public function search(User $viewer, string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';

        $people = User::where('name', 'like', $like)->orderBy('name')->limit(5)->get()
            ->map(fn (User $user) => [
                'type' => 'user', 'id' => $user->id, 'name' => $user->name,
                'detail' => $user->isOfficialAccount() ? 'Official' : 'Member',
            ]);

        $businesses = Team::where('personal_team', false)->where('name', 'like', $like)
            ->where(fn ($q) => $q
                ->whereIn('id', Questions::withoutGlobalScopes()->where('visibility', Questions::VISIBILITY_PUBLIC)->select('team_id'))
                ->orWhereIn('id', Solutions::withoutGlobalScopes()->where('visibility', Solutions::VISIBILITY_PUBLIC)->select('team_id'))
                ->orWhere('id', $viewer->current_team_id))
            ->orderBy('name')->limit(3)->get()
            ->map(fn (Team $team) => ['type' => 'business', 'id' => $team->id, 'name' => $team->name, 'detail' => 'Business']);

        return $people->concat($businesses)->values()->all();
    }

    /**
     * The hidden JSON field a composer carries, turned into record() items. Garbage is a 422, never a crash.
     *
     * @return list<array{type: string, id: int}>
     */
    public static function parsePayload(?string $json): array
    {
        $json = trim((string) $json);

        if ($json === '' || $json === '[]') {
            return [];
        }

        $rows = json_decode($json, true);

        if (! is_array($rows) || count($rows) > self::MAX_PER_ITEM) {
            throw ValidationException::withMessages(['mentions' => 'The mentions could not be read. Remove them and pick the people again.']);
        }

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! in_array($row['type'] ?? '', ['user', 'business'], true) || (int) ($row['id'] ?? 0) < 1) {
                throw ValidationException::withMessages(['mentions' => 'The mentions could not be read. Remove them and pick the people again.']);
            }
            $items[] = ['type' => (string) $row['type'], 'id' => (int) $row['id']];
        }

        return $items;
    }

    /**
     * Record the mentions a composer collected and tell the people involved. Called after the content exists; a bad
     * payload is a 422, and more than a handful of mentions per item or per hour is refused (anti-abuse).
     *
     * @param  list<array{type?: string, id?: int|string}>  $items
     */
    public function record(Model $mentionable, User $mentioner, array $items): void
    {
        if ($items === []) {
            return;
        }

        if (count($items) > self::MAX_PER_ITEM) {
            throw ValidationException::withMessages(['mentions' => 'Mention at most '.self::MAX_PER_ITEM.' people or businesses on one item.']);
        }

        if (! RateLimiter::attempt('mentions:'.$mentioner->id, self::PER_HOUR, fn () => true, 3600)) {
            throw ValidationException::withMessages(['mentions' => 'You are mentioning a lot of people in a short time. Try again later.']);
        }

        $subject = $this->subjectOf($mentionable);

        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            $id = (int) ($item['id'] ?? 0);

            if ($type === 'user') {
                $user = User::find($id);
                if (! $user || $user->is($mentioner)) {
                    continue;
                }
                if ($this->store($mentionable, $mentioner, ['mentioned_user_id' => $user->id])) {
                    $this->tell($user, $mentioner, $mentionable, $subject);
                }
            } elseif ($type === 'business') {
                $team = Team::where('personal_team', false)->find($id);
                if (! $team) {
                    continue;
                }
                if ($this->store($mentionable, $mentioner, ['mentioned_team_id' => $team->id])) {
                    foreach ($this->representatives($team) as $representative) {
                        $this->tell($representative, $mentioner, $mentionable, $subject);
                    }
                }
            } else {
                throw ValidationException::withMessages(['mentions' => 'A mention could not be read. Remove it and pick the person again.']);
            }
        }
    }

    /** @param array<string, int> $who */
    private function store(Model $mentionable, User $mentioner, array $who): bool
    {
        try {
            $mention = new Mention($who + ['mentioner_id' => $mentioner->id, 'created_at' => now()]);
            $mention->mentionable()->associate($mentionable);
            $mention->save();

            return true;
        } catch (UniqueConstraintViolationException) {
            return false; // mentioned twice on the same item: the first one holds, nobody is notified again
        }
    }

    /** The question or solution whose page the notice links to. @return array{0: string, 1: int}|null */
    private function subjectOf(Model $mentionable): ?array
    {
        if ($mentionable instanceof Questions) {
            return ['question', (int) $mentionable->getKey()];
        }
        if ($mentionable instanceof Answer) {
            return ['question', (int) $mentionable->question_id];
        }
        if ($mentionable instanceof Comment) {
            $root = $mentionable->commentable()->withoutGlobalScopes()->first();

            if ($root instanceof Questions) {
                return ['question', (int) $root->getKey()];
            }
            if ($root instanceof Solutions) {
                return ['solution', (int) $root->getKey()];
            }
        }
        if ($mentionable instanceof Solutions) {
            return ['solution', (int) $mentionable->getKey()];
        }

        return null;
    }

    private function tell(User $recipient, User $mentioner, Model $mentionable, ?array $subject): void
    {
        if ($subject === null) {
            return;
        }

        try {
            app(InAppNotices::class)->record(
                UserNotice::MENTIONED, $recipient, $subject[0], $subject[1],
                'infodot:mention:'.Str::snake(class_basename($mentionable)).':'.$mentionable->getKey(), $mentioner,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The people who speak for a business: its owner and admins, capped so one mention never fans out widely. */
    private function representatives(Team $team): array
    {
        $reps = collect([$team->owner])
            ->concat($team->users()->wherePivot('role', 'admin')->get())
            ->filter()->unique('id')->take(5)->values();

        return $reps->all();
    }
}
