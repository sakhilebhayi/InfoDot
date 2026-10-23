<?php

namespace App\Services\Participation;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * The ONE definition of what InfoDot may tell Dot.Memory about a person who opted in. Used for the live event after
 * an acceptance or a verification and for the backfill when someone switches sharing on, so the two can never drift.
 *
 * Eligible, and nothing else:
 *  - an answer of theirs, published and not hidden, that somebody OTHER than them accepted, on a public question that is
 *    not hidden (kind "accepted"); and
 *  - a public solution of theirs, not hidden, that a moderator OTHER than them verified (kind "verified").
 * One event per topic the item is filed under; an item with no topic yields nothing. Each event carries only a topic
 * slug, the kind, an opaque reference and a time. The event id is deterministic (a UUIDv5 of reference, topic and kind),
 * so sending the same fact twice is counted once by Memory, and accepting the same answer again after a revoke is not
 * a second count.
 */
final class EligibleContributions
{
    /**
     * @return list<array{event_id: string, user_uuid: string, occurred_at: string, topic: string, kind: string, contribution_ref: string}>
     */
    public static function forUser(User $user, ?string $onlyRef = null): array
    {
        $events = [];
        $oldest = Carbon::now()->subDays(max(1, (int) config('participation.backfill_max_age_days', 365)));
        $uuid = (string) $user->uuid;

        $wantAnswers = $onlyRef === null || str_starts_with($onlyRef, 'infodot:answer:');
        $wantSolutions = $onlyRef === null || str_starts_with($onlyRef, 'infodot:solution:');

        $answers = ! $wantAnswers ? collect() : Answer::withoutGlobalScope('moderation-visibility')
            ->where('answers.user_id', $user->id)->where('answers.status', Answer::PUBLISHED)->whereNull('answers.hidden_at')
            ->where('answers.is_self_answer', false)
            ->whereNotNull('answers.accepted_at')->whereNotNull('answers.accepted_by')->where('answers.accepted_by', '!=', $user->id)
            ->whereIn('answers.question_id', Questions::withoutGlobalScopes()->openToAll()->select('questions.id'))
            ->when($onlyRef !== null, fn ($q) => $q->where('answers.id', (int) preg_replace('/\D/', '', (string) $onlyRef)))
            ->get(['answers.id', 'answers.question_id', 'answers.accepted_at']);

        foreach ($answers as $answer) {
            $ref = $answer->reference();
            if ($onlyRef !== null && $ref !== $onlyRef) {
                continue;
            }
            foreach (self::slugs('question_topic', 'question_id', $answer->question_id) as $slug) {
                $events[] = self::event($uuid, $ref, $slug, 'accepted', $answer->accepted_at);
            }
        }

        $solutions = ! $wantSolutions ? collect() : Solutions::withoutGlobalScopes()->openToAll()
            ->where('solutions.user_id', $user->id)
            ->whereNotNull('solutions.verified_at')->whereNotNull('solutions.verified_by')->where('solutions.verified_by', '!=', $user->id)
            ->when($onlyRef !== null, fn ($q) => $q->where('solutions.id', (int) preg_replace('/\D/', '', (string) $onlyRef)))
            ->get(['solutions.id', 'solutions.verified_at']);

        foreach ($solutions as $solution) {
            $ref = 'infodot:solution:'.$solution->id;
            if ($onlyRef !== null && $ref !== $onlyRef) {
                continue;
            }
            foreach (self::slugs('solution_topic', 'solution_id', $solution->id) as $slug) {
                $events[] = self::event($uuid, $ref, $slug, 'verified', Carbon::parse($solution->verified_at));
            }
        }

        return array_values(array_filter($events, fn (array $e) => Carbon::parse($e['occurred_at'])->gte($oldest)));
    }

    /** The same event id however often the same fact is raised. */
    public static function eventId(string $ref, string $topic, string $kind): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'infodot:participation:'.$ref.':'.$topic.':'.$kind)->toString();
    }

    /** @return array{event_id: string, user_uuid: string, occurred_at: string, topic: string, kind: string, contribution_ref: string} */
    private static function event(string $uuid, string $ref, string $slug, string $kind, ?Carbon $at): array
    {
        return [
            'event_id' => self::eventId($ref, $slug, $kind),
            'user_uuid' => $uuid,
            'occurred_at' => ($at ?? Carbon::now())->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'topic' => $slug,
            'kind' => $kind,
            'contribution_ref' => $ref,
        ];
    }

    /** @return list<string> the slugs of the topics an item is filed under that Memory will accept (lowercase slug, at most 64). */
    private static function slugs(string $linkTable, string $key, int $itemId): array
    {
        return DB::table($linkTable)->join('topics', 'topics.id', '=', $linkTable.'.topic_id')
            ->where($linkTable.'.'.$key, $itemId)->orderBy('topics.slug')->pluck('topics.slug')
            ->filter(fn ($slug) => is_string($slug) && strlen($slug) <= 64 && preg_match('/\A[a-z0-9]+(-[a-z0-9]+)*\z/', $slug))
            ->values()->all();
    }
}
