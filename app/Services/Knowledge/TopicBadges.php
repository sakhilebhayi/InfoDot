<?php

namespace App\Services\Knowledge;

use App\Models\Questions;
use App\Models\User;
use App\Models\UserTopicBadge;
use App\Support\Knowledge\BadgeLevels;
use Illuminate\Support\Facades\DB;

/**
 * Specialist badges. `sync()` makes a person's stored badges equal to what their current work earns (AchievementService::sync
 * calls it, so a badge follows the work: taken back when the work is hidden or un-accepted, back again when the work returns).
 * The read methods decide what other people may see: only badges of people who have not hidden them, only for a topic a PUBLIC
 * question is filed under. Nothing here changes a permission, a trust decision or the order of any content.
 */
class TopicBadges
{
    public function __construct(private readonly BadgeLevels $levels) {}

    /**
     * @param  array<int, int>  $workByTopic  topic id => qualifying work items (see AchievementService::workByTopic)
     * @return array{awarded: int, changed: int, removed: int}
     */
    public function sync(User $user, array $workByTopic): array
    {
        $version = $this->levels->version();
        $rows = UserTopicBadge::where('user_id', $user->id)->get()->keyBy('topic_id');
        $changes = ['awarded' => 0, 'changed' => 0, 'removed' => 0];

        foreach ($workByTopic as $topicId => $work) {
            $level = $this->levels->levelFor((int) $work);
            $row = $rows->get($topicId);

            if ($level === null) {
                continue; // handled below with the rest that are no longer earned
            }

            if ($row === null) {
                UserTopicBadge::updateOrCreate(['user_id' => $user->id, 'topic_id' => $topicId], ['level' => $level, 'work_count' => $work, 'rules_version' => $version]);
                $changes['awarded']++;
            } elseif ($row->level !== $level || $row->work_count !== (int) $work || $row->rules_version !== $version) {
                $row->forceFill(['level' => $level, 'work_count' => $work, 'rules_version' => $version])->save();
                $changes['changed']++;
            }
        }

        foreach ($rows as $topicId => $row) {
            if ($this->levels->levelFor((int) ($workByTopic[$topicId] ?? 0)) === null) {
                $row->delete();
                $changes['removed']++;
            }
        }

        return $changes;
    }

    /**
     * The badge to show beside each answer author on a question page: for each author, only a topic the QUESTION is filed under,
     * the highest level when there are several. Two queries however many answers there are. A private or company-only question
     * shows no badges at all, so a badge can never hint at who works on what inside a company.
     *
     * @param  list<int>  $authorIds
     * @return array<int, array{topic: string, slug: string, level: string, level_name: string}> user id => badge
     */
    public function forQuestion(Questions $question, array $authorIds): array
    {
        $authorIds = array_values(array_unique($authorIds));

        if ($authorIds === [] || ! $question->isPublic() || $question->isHidden()) {
            return [];
        }

        $topicIds = DB::table('question_topic')->where('question_id', $question->id)->pluck('topic_id')->all();

        if ($topicIds === []) {
            return [];
        }

        $best = [];
        $badges = UserTopicBadge::query()
            ->join('users', 'users.id', '=', 'user_topic_badges.user_id')->where('users.badges_visible', true)
            ->join('topics', 'topics.id', '=', 'user_topic_badges.topic_id')
            ->whereIn('user_topic_badges.user_id', $authorIds)->whereIn('user_topic_badges.topic_id', $topicIds)
            ->get(['user_topic_badges.user_id', 'user_topic_badges.level', 'user_topic_badges.work_count', 'topics.name', 'topics.slug']);

        foreach ($badges as $b) {
            $current = $best[$b->user_id] ?? null;

            if ($current === null || [$this->levels->rank($b->level), $b->work_count] > [$this->levels->rank($current['level']), $current['work']]) {
                $best[$b->user_id] = ['topic' => (string) $b->name, 'slug' => (string) $b->slug, 'level' => $b->level, 'level_name' => $this->levels->name($b->level), 'work' => (int) $b->work_count];
            }
        }

        return array_map(fn (array $b) => array_diff_key($b, ['work' => 1]), $best);
    }

    /**
     * What a profile lists, highest level first, then topic name. Empty for a person who hid their badges, whoever is looking,
     * except the person themselves, who always sees their own so they know what the switch hides.
     *
     * @return list<array{topic: string, slug: string, level: string, level_name: string, work_count: int}>
     */
    public function forProfile(User $user, bool $ownerView = false): array
    {
        if (! $ownerView && ! $user->showsBadges()) {
            return [];
        }

        return UserTopicBadge::where('user_id', $user->id)->with('topic:id,name,slug')->get()
            ->filter(fn (UserTopicBadge $b) => $b->topic !== null)
            ->map(fn (UserTopicBadge $b) => ['topic' => (string) $b->topic->name, 'slug' => (string) $b->topic->slug, 'level' => $b->level, 'level_name' => $this->levels->name($b->level), 'work_count' => $b->work_count])
            ->sort(fn (array $a, array $b) => [$this->levels->rank($b['level']), $a['topic']] <=> [$this->levels->rank($a['level']), $b['topic']])
            ->values()->all();
    }
}
