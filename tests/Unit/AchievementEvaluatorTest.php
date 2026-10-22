<?php

namespace Tests\Unit;

use App\Support\Knowledge\AchievementEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The milestone rules are pure functions of counts, so every boundary is tested with plain arrays. */
class AchievementEvaluatorTest extends TestCase
{
    private function evaluator(): AchievementEvaluator
    {
        $catalogue = require dirname(__DIR__, 2).'/config/achievements.php';

        return new AchievementEvaluator($catalogue['achievements']);
    }

    /** @return array<string, array{0: string, 1: int, 2: string, 3: bool}> */
    public static function boundaries(): array
    {
        return [
            'no questions' => ['questions', 0, 'first_question', false],
            'one question' => ['questions', 1, 'first_question', true],
            'nine questions' => ['questions', 9, 'questions_10', false],
            'ten questions' => ['questions', 10, 'questions_10', true],
            'no answers' => ['answers', 0, 'first_answer', false],
            'one answer' => ['answers', 1, 'first_answer', true],
            'nine answers' => ['answers', 9, 'answers_10', false],
            'ten answers' => ['answers', 10, 'answers_10', true],
            'forty-nine answers' => ['answers', 49, 'answers_50', false],
            'fifty answers' => ['answers', 50, 'answers_50', true],
            'none accepted' => ['accepted', 0, 'first_accepted', false],
            'one accepted' => ['accepted', 1, 'first_accepted', true],
            'four accepted' => ['accepted', 4, 'accepted_5', false],
            'five accepted' => ['accepted', 5, 'accepted_5', true],
            'twenty-four accepted' => ['accepted', 24, 'accepted_25', false],
            'twenty-five accepted' => ['accepted', 25, 'accepted_25', true],
            'none verified' => ['verified', 0, 'first_verified', false],
            'one verified' => ['verified', 1, 'first_verified', true],
            'four verified' => ['verified', 4, 'verified_5', false],
            'five verified' => ['verified', 5, 'verified_5', true],
        ];
    }

    /** @dataProvider boundaries */
    #[DataProvider('boundaries')]
    public function test_each_milestone_is_met_exactly_at_its_threshold(string $metric, int $count, string $key, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluator()->met([$metric => $count])[$key]);
    }

    public function test_one_metric_never_meets_another_metrics_milestones(): void
    {
        $met = $this->evaluator()->met(['answers' => 100]);

        $this->assertTrue($met['answers_50']);
        $this->assertFalse($met['first_question']);
        $this->assertFalse($met['first_accepted']);
        $this->assertFalse($met['first_verified']);
    }

    public function test_missing_and_negative_counts_meet_nothing(): void
    {
        $this->assertSame([], array_keys(array_filter($this->evaluator()->met([]))));
        $this->assertSame([], array_keys(array_filter($this->evaluator()->met(['questions' => -3]))));
    }

    public function test_the_catalogue_is_exactly_the_agreed_set_and_has_no_points_or_time_based_rules(): void
    {
        $config = require dirname(__DIR__, 2).'/config/achievements.php';

        $this->assertSame(
            ['first_question', 'questions_10', 'first_answer', 'answers_10', 'answers_50', 'first_accepted', 'accepted_5', 'accepted_25', 'first_verified', 'verified_5'],
            array_keys($config['achievements']),
        );

        foreach ($config['achievements'] as $key => $rule) {
            $this->assertArrayHasKey($rule['metric'], $config['metrics'], $key);
            $this->assertNotSame('', trim($rule['meaning']), $key);
            $this->assertSame(['name', 'meaning', 'metric', 'threshold'], array_keys($rule), $key.' has only a name, a meaning and a count rule');
        }
    }

    public function test_next_is_the_nearest_unmet_milestone_of_each_metric_with_progress(): void
    {
        $next = collect($this->evaluator()->next(['questions' => 1, 'answers' => 3, 'accepted' => 4, 'verified' => 5]))->keyBy('metric');

        $this->assertSame(['key' => 'questions_10', 'name' => 'Ten questions', 'meaning' => $next['questions']['meaning'], 'metric' => 'questions', 'current' => 1, 'target' => 10], $next['questions']);
        $this->assertSame([3, 10], [$next['answers']['current'], $next['answers']['target']]);
        $this->assertSame([4, 5], [$next['accepted']['current'], $next['accepted']['target']]);
        $this->assertArrayNotHasKey('verified', $next->all(), 'everything for verified solutions is already met');
    }
}
