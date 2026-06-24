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

    public function test_score_restores_output_buffer_level(): void
    {
        // ScoreEngine drains the output buffers it (and eval'd question code)
        // opens, down to — but not below — the level it found. If it over- or
        // under-drained, the surrounding output buffering would break and stray
        // output could leak into the JSON response. Level must be unchanged.
        $before = ob_get_level();

        $this->service()->score(ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$answer = \$a",
            'seed' => 1234,
            'answer' => '5',
        ]));

        self::assertSame($before, ob_get_level());
    }

    public function test_engine_errors_ride_along_in_result(): void
    {
        // Control that throws during eval (call to an undefined function). The
        // engine catches it via its eval exception handler and records a soft
        // error rather than fatalling, so scoring still returns a result and
        // the domain error must be surfaced in $result->errors.
        $result = $this->service()->score(ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$answer = nonexistentfunc();",
            'seed' => 1234,
            'answer' => '12',
        ]));

        self::assertNotEmpty($result->errors);
    }
}
