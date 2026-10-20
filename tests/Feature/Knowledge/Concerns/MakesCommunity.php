<?php

namespace Tests\Feature\Knowledge\Concerns;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use Illuminate\Support\Carbon;

/** People, questions, answers and solutions for the moderation tests. */
trait MakesCommunity
{
    private const GOOD = 'First register the business with CIPC, then open a bank account, then ask your supplier for terms in writing.';

    private function person(string $name = 'Person'): User
    {
        $user = User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function moderator(string $name = 'Moderator'): User
    {
        $user = $this->person($name);
        $user->forceFill(['is_moderator' => true])->save();

        return $user->fresh();
    }

    private function question(User $asker, string $visibility = 'public', string $title = 'How do I register a clothing brand?'): Questions
    {
        return Questions::create([
            'user_id' => $asker->id, 'team_id' => $asker->current_team_id, 'visibility' => $visibility,
            'question' => $title, 'description' => 'I have never done this before and want the order of steps.',
        ]);
    }

    private function answer(Questions $question, User $author, string $body = self::GOOD): Answer
    {
        return app(AnswerService::class)->publish($question, $author, $body);
    }

    private function solution(User $author, string $visibility = 'public', string $title = 'Register a company with CIPC'): Solutions
    {
        return Solutions::create([
            'user_id' => $author->id, 'team_id' => $author->current_team_id, 'visibility' => $visibility,
            'solution_title' => $title, 'solution_description' => 'The order of the steps matters.', 'tags' => 'cipc, company',
            'duration' => 2, 'duration_type' => 'days', 'steps' => 0,
        ]);
    }
}
