<?php

namespace App\Services\Knowledge;

use App\Models\ModerationAction;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Who may file things under which topics, and who may change the list. Like ModerationService this is where the rules
 * live: the screens only ask. Every change a moderator makes is written to the append-only moderation trail in the same
 * transaction as the change, and repeating one that is already true changes nothing and writes nothing.
 */
class TopicService
{
    public const DESCRIPTION_MAX = 255;

    public function __construct(private readonly AnswerService $answers) {}

    /**
     * The topics a person may choose from when they ask or write: the active ones, in display order.
     *
     * @return Collection<int, Topic>
     */
    public function choices(): Collection
    {
        return Topic::active()->ordered()->get();
    }

    /**
     * What a moderator can pick when correcting this item: the active topics, plus any switched-off topic it already carries
     * (so a correction does not have to drop a label just because the topic was retired).
     *
     * @return Collection<int, Topic>
     */
    public function choicesFor(Model $item): Collection
    {
        return Topic::ordered()->where(fn ($q) => $q->where('topics.active', true)->orWhereIn('topics.id', $item->topics()->select('topics.id')))->get();
    }

    /** Validation for the `topics[]` field of a form that files new content (1 to 3 active topics, no repeats). */
    public static function rules(): array
    {
        return [
            'topics' => ['required', 'array', 'min:1', 'max:'.Topic::MAX_PER_ITEM],
            'topics.*' => ['integer', 'distinct', Rule::exists('topics', 'id')->where('active', true)],
        ];
    }

    public static function messages(): array
    {
        return [
            'topics.required' => 'Choose at least one topic, so people who know the subject can find this.',
            'topics.array' => 'Choose at least one topic, so people who know the subject can find this.',
            'topics.min' => 'Choose at least one topic, so people who know the subject can find this.',
            'topics.max' => 'Choose up to '.Topic::MAX_PER_ITEM.' topics.',
            'topics.*.exists' => 'One of the topics you chose is not available any more. Choose again.',
            'topics.*.integer' => 'One of the topics you chose is not valid. Choose again.',
            'topics.*.distinct' => 'Each topic can only be chosen once.',
        ];
    }

    /** File new content under the chosen topics. The ids have already passed rules(). */
    public function file(Model $item, array $topicIds): void
    {
        $item->topics()->sync(array_values(array_unique(array_map('intval', $topicIds))));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The list itself. Moderators only.
    // ---------------------------------------------------------------------------------------------------------------

    public function create(User $by, string $name, ?string $description = null): Topic
    {
        abort_unless($by->isModerator(), 403);
        $name = $this->cleanName($name, 'name');
        $description = $this->cleanDescription($description, 'description');
        $slug = Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages(['name' => 'Use letters or numbers in the name.']);
        }

        try {
            return DB::transaction(function () use ($by, $name, $description, $slug): Topic {
                if (Topic::where('slug', $slug)->exists() || $this->nameTaken($name, null)) {
                    throw ValidationException::withMessages(['name' => 'There is already a topic with that name.']);
                }

                $topic = Topic::create([
                    'slug' => $slug, 'name' => $name, 'description' => $description, 'active' => true,
                    'sort_order' => min(65000, ((int) Topic::max('sort_order')) + 10),
                ]);
                ModerationAction::record($topic, $by, ModerationAction::TOPIC_ADDED, $name);

                return $topic;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'There is already a topic with that name.']);
        }
    }

    /**
     * Change a topic's name or description. Its address (slug) stays, so links and bookmarks keep working.
     *
     * @return bool false when nothing changed
     */
    public function rename(Topic $topic, User $by, string $name, ?string $description = null): bool
    {
        abort_unless($by->isModerator(), 403);
        $name = $this->cleanName($name, 'name');
        $description = $this->cleanDescription($description, 'description');

        return DB::transaction(function () use ($topic, $by, $name, $description): bool {
            $row = Topic::lockForUpdate()->findOrFail($topic->getKey());

            if ($this->nameTaken($name, $row->getKey())) {
                throw ValidationException::withMessages(['name' => 'There is already a topic with that name.']);
            }

            if ($row->name === $name && $row->description === $description) {
                return false;
            }

            $what = [];
            if ($row->name !== $name) {
                $what[] = 'Name: “'.$row->name.'” to “'.$name.'”.';
            }
            if ($row->description !== $description) {
                $what[] = 'Description changed.';
            }

            $row->forceFill(['name' => $name, 'description' => $description])->save();
            ModerationAction::record($row, $by, ModerationAction::TOPIC_RENAMED, implode(' ', $what));

            return true;
        });
    }

    /**
     * Switch a topic off (new questions and solutions can no longer choose it; what is already filed under it stays) or
     * back on. At least one topic must stay on, or nobody could ask.
     *
     * @return bool false when it was already in that state
     */
    public function setActive(Topic $topic, User $by, bool $active): bool
    {
        abort_unless($by->isModerator(), 403);

        return DB::transaction(function () use ($topic, $by, $active): bool {
            $row = Topic::lockForUpdate()->findOrFail($topic->getKey());

            if ($row->active === $active) {
                return false;
            }

            if (! $active && Topic::active()->where('id', '!=', $row->getKey())->doesntExist()) {
                throw ValidationException::withMessages(['topic' => 'At least one topic has to stay on, or nobody could ask a question.']);
            }

            $row->forceFill(['active' => $active])->save();
            ModerationAction::record($row, $by, $active ? ModerationAction::TOPIC_ACTIVATED : ModerationAction::TOPIC_DEACTIVATED, $row->name);

            return true;
        });
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Correcting what an item is filed under. Moderators only.
    // ---------------------------------------------------------------------------------------------------------------

    /** A moderator, for a question or solution they can see that is not their own. */
    public function canCorrect(Model $item, User $by): bool
    {
        return ($item instanceof Questions || $item instanceof Solutions)
            && $by->isModerator()
            && $item->user_id !== $by->id
            && $this->answers->canSeeContent($item, $by);
    }

    /**
     * Replace the topics a question or solution is filed under (1 to 3). A topic that has since been switched off may be
     * kept if the item already carries it, but cannot be newly chosen. The trail records what it was and what it became.
     *
     * @param  list<int|string>  $topicIds
     * @return bool false when the topics were already these
     */
    public function correct(Model $item, User $by, array $topicIds, ?string $note = null): bool
    {
        abort_unless($item instanceof Questions || $item instanceof Solutions, 404);
        abort_unless($by->isModerator(), 403);
        abort_unless($this->answers->canSeeContent($item, $by), 403);
        abort_if($item->user_id === $by->id, 403, 'You cannot moderate your own content.');

        $ids = collect($topicIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();

        if ($ids->isEmpty() || $ids->count() > Topic::MAX_PER_ITEM) {
            throw ValidationException::withMessages(['topics' => 'Choose between 1 and '.Topic::MAX_PER_ITEM.' topics.']);
        }

        $note = trim((string) $note);
        if (mb_strlen($note) > ModerationService::NOTE_MAX) {
            throw ValidationException::withMessages(['note' => 'Keep the note under '.ModerationService::NOTE_MAX.' characters.']);
        }

        return DB::transaction(function () use ($item, $by, $ids, $note): bool {
            $before = $item->topics()->get();
            $beforeIds = $before->pluck('id')->map(fn ($id) => (int) $id);
            $chosen = Topic::whereIn('id', $ids)->get();

            if ($chosen->count() !== $ids->count()) {
                throw ValidationException::withMessages(['topics' => 'One of the topics you chose does not exist.']);
            }

            $switchedOff = $chosen->filter(fn (Topic $t) => ! $t->active && ! $beforeIds->contains($t->id));
            if ($switchedOff->isNotEmpty()) {
                throw ValidationException::withMessages(['topics' => '“'.$switchedOff->first()->name.'” is switched off, so it cannot be newly chosen.']);
            }

            if ($beforeIds->sort()->values()->all() === $ids->sort()->values()->all()) {
                return false;
            }

            $item->topics()->sync($ids->all());
            $item->touch(); // So "changed since" (the candidates feed for Dot.Brain) sees the new topics.

            $names = fn ($topics) => $topics->isEmpty() ? 'none' : $topics->sortBy(['sort_order', 'id'])->pluck('name')->implode(', ');
            $reason = 'From: '.$names($before).'. To: '.$names($chosen).'.'.($note !== '' ? ' '.$note : '');
            ModerationAction::record($item, $by, ModerationAction::TOPICS_SET, $reason);

            return true;
        });
    }

    // ---------------------------------------------------------------------------------------------------------------

    private function cleanName(string $name, string $field): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if (mb_strlen($name) < Topic::NAME_MIN || mb_strlen($name) > Topic::NAME_MAX) {
            throw ValidationException::withMessages([$field => 'Give the topic a name of '.Topic::NAME_MIN.' to '.Topic::NAME_MAX.' characters.']);
        }

        return $name;
    }

    private function cleanDescription(?string $description, string $field): ?string
    {
        $description = trim((string) $description);

        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            throw ValidationException::withMessages([$field => 'Keep the description under '.self::DESCRIPTION_MAX.' characters.']);
        }

        return $description === '' ? null : $description;
    }

    private function nameTaken(string $name, ?int $exceptId): bool
    {
        return Topic::whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
