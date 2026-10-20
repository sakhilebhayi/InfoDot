<?php

namespace App\Support\Knowledge;

use App\Models\Answer;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Models\User;
use App\Services\Knowledge\ModerationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What a person should know about moderation of their own content: what is hidden and why, where each appeal stands, and
 * which appeals were upheld. InfoDot has no notification inbox yet, so this (and the notice on the item) is how they find out.
 * It only ever describes the person's own content.
 */
final class ModerationStatus
{
    /** @return Collection<int, array{kind: string, title: string, url: string, hidden: bool, reason: ?string, at: ?Carbon, appeal: ?ModerationAppeal}> */
    public static function for(User $user): Collection
    {
        $scopes = ['moderation-visibility', 'knowledge-visibility'];
        $rows = collect();

        foreach ([ModerationService::KINDS['question'], ModerationService::KINDS['solution'], ModerationService::KINDS['answer']] as $class) {
            $class::withoutGlobalScopes($scopes)->where('user_id', $user->id)->whereNotNull('hidden_at')->orderByDesc('hidden_at')->get()
                ->each(function (Model $item) use ($rows) {
                    $hide = ModerationAction::where('subject_type', $item->getMorphClass())->where('subject_id', $item->getKey())
                        ->where('action', ModerationAction::HIDDEN)->orderByDesc('id')->first();

                    $rows->push(self::row($item, true, $item->hidden_reason, $item->hidden_at, $hide
                        ? ModerationAppeal::where('subject_type', $item->getMorphClass())->where('subject_id', $item->getKey())->where('hide_action_id', $hide->id)->first()
                        : null));
                });
        }

        ModerationAppeal::where('appellant_id', $user->id)->where('status', ModerationAppeal::UPHELD)->orderByDesc('decided_at')->limit(5)->get()
            ->each(function (ModerationAppeal $appeal) use ($rows) {
                $item = app(ModerationService::class)->resolve($appeal->subject_type, $appeal->subject_id);

                if ($item !== null && ! $item->isHidden()) {
                    $rows->push(self::row($item, false, $appeal->decision_note, $appeal->decided_at, $appeal));
                }
            });

        return $rows->values();
    }

    private static function row(Model $item, bool $hidden, ?string $reason, $at, ?ModerationAppeal $appeal): array
    {
        return match (true) {
            $item instanceof Answer => ['kind' => 'Answer', 'title' => Str::limit((string) $item->body, 90), 'url' => route('questions.view', ['qid' => $item->question_id]).'#answer-'.$item->id],
            $item instanceof Questions => ['kind' => 'Question', 'title' => Str::limit((string) $item->question, 90), 'url' => route('questions.view', ['qid' => $item->id])],
            default => ['kind' => 'Solution', 'title' => Str::limit((string) $item->solution_title, 90), 'url' => route('solutions.view', ['id' => $item->id])],
        } + ['hidden' => $hidden, 'reason' => $reason, 'at' => $at, 'appeal' => $appeal];
    }
}
