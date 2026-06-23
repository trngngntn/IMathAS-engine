<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\QuestionService;
use PHPUnit\Framework\TestCase;

final class QuestionServiceScoreTest extends TestCase
{
    private function service(): QuestionService
    {
        Bootstrap::init();
        require_once dirname(__DIR__, 2) . '/assess2/AssessStandalone.php';
        return new QuestionService($GLOBALS['DBH']);
    }

    public function test_correct_answer_scores_full(): void
    {
        $result = $this->service()->score(ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$b = 7\n\$answer = \$a + \$b",
            'seed' => 1234,
            'answer' => '12',
        ]));

        self::assertEqualsWithDelta(1.0, $result->scores[0] ?? 0, 0.01);
        self::assertTrue($result->allAnswered);
    }

    public function test_wrong_answer_scores_zero(): void
    {
        $result = $this->service()->score(ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$b = 7\n\$answer = \$a + \$b",
            'seed' => 1234,
            'answer' => '99',
        ]));

        self::assertEqualsWithDelta(0.0, $result->scores[0] ?? 1, 0.01);
        self::assertTrue($result->allAnswered);
    }
}
