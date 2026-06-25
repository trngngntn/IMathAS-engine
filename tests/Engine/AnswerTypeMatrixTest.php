<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\QuestionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Breadth coverage across the supported answer types, exercised against the
 * REAL engine. This is the regression net for the strip-down: if a future
 * change removes a file/macro/library that an answer type needs, a type that
 * used to render or score will start failing here.
 *
 * Seeds are fixed so every case is deterministic.
 */
final class AnswerTypeMatrixTest extends TestCase
{
    private const SEED = 1234;

    private function service(): QuestionService
    {
        Bootstrap::init();
        require_once dirname(__DIR__, 2) . '/assess2/AssessStandalone.php';
        return new QuestionService($GLOBALS['DBH']);
    }

    /**
     * Every supported qtype must render to non-empty question HTML with no
     * engine-reported errors. `control` includes the $answer assignment per
     * the engine convention; `qtext` places the answer box(es).
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function renderCases(): array
    {
        return [
            'number'       => ['number',       "\$a = 5\n\$answer = \$a + 1",                         'Find it: $answerbox'],
            'calculated'   => ['calculated',   "\$a = 6\n\$answer = \$a / 2",                          'Compute: $answerbox'],
            'calc w/ lib'  => ['calculated',   "loadlibrary(\"stats\")\n\$d = array(2,4,6)\n\$answer = mean(\$d)", 'Mean: $answerbox'],
            'choices'      => ['choices',      "\$questions[0] = 'a'\n\$questions[1] = 'b'\n\$answer = 0", 'Pick:'],
            'multans'      => ['multans',      "\$questions = listtoarray(\"a,b,c\")\n\$answers = \"0,2\"", 'Select all:'],
            'matching'     => ['matching',     "\$qarr = array(\"x\",\"y\")\n\$aarr = array(\"1\",\"2\")\n\$questions,\$answers = jointshuffle(\$qarr,\$aarr,2,2)", 'Match:'],
            'numfunc'      => ['numfunc',      "\$variables = \"x\"\n\$answer = \"x^2\"",             'f(x)= $answerbox'],
            'matrix'       => ['matrix',       "loadlibrary(\"matrix\")\n\$m = matrix(array(1,2,3,4),2,2)\n\$answer = matrixformat(\$m)\n\$answersize = \"2,2\"", 'Matrix: $answerbox'],
            'calcmatrix'   => ['calcmatrix',   "loadlibrary(\"matrix\")\n\$m = matrix(array(1,2,3,4),2,2)\n\$answer = matrixformat(\$m)\n\$answersize = \"2,2\"", 'Matrix: $answerbox'],
            'interval'     => ['interval',     "loadlibrary(\"interval\")\n\$answer = \"(-oo,3]\"",  'Interval: $answerbox'],
            'calcinterval' => ['calcinterval', "\$answer = \"[2,5)\"",                                 'Domain: $answerbox'],
            'ntuple'       => ['ntuple',       "\$answer = \"(1,0)\"\n\$displayformat = \"pointlist\"", 'Point: $answerbox'],
            'calcntuple'   => ['calcntuple',   "\$answer = \"<1,2>\"\n\$displayformat = \"vector\"",  'Vector: $answerbox'],
            'string'       => ['string',       "\$answer = \"hello\"",                                 'Type it: $answerbox'],
            'draw'         => ['draw',         "\$grid = \"-5,5,-5,5\"\n\$answers = \"x^2-1\"\n\$answerformat = \"line\"", 'Sketch the curve'],
            'multipart'    => ['multipart',    "\$anstypes = array(\"number\",\"number\")\n\$answer[0] = 3\n\$answer[1] = 4", 'x=$answerbox[0] y=$answerbox[1]'],
            'conditional'  => ['conditional',  "\$anstypes = array(\"number\")\n\$answer[0] = 5",     'Value: $answerbox[0]'],
        ];
    }

    #[DataProvider('renderCases')]
    public function test_qtype_renders_cleanly(string $qtype, string $control, string $qtext): void
    {
        $result = $this->service()->render(RenderRequest::fromArray([
            'qtype' => $qtype,
            'control' => $control,
            'qtext' => $qtext,
            'seed' => self::SEED,
        ]));

        self::assertSame([], $result->errors, "engine reported errors for qtype=$qtype");
        self::assertNotSame('', trim($result->question), "empty question HTML for qtype=$qtype");
    }

    /**
     * For the answer types with a single, simply-expressed correct answer, a
     * correct submission must score a perfect 1.0. The single answer box is
     * `qn0` at slot 0. (Matrix needs per-cell sub-keys; multipart scoring is
     * covered in QuestionServiceScoreTest.)
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function scoreCases(): array
    {
        return [
            'number'       => ['number',       "\$a = 5\n\$answer = \$a + 1",        '6'],
            'calculated'   => ['calculated',   "\$a = 6\n\$answer = \$a / 2",         '3'],
            'choices'      => ['choices',      "\$questions[0] = 'a'\n\$questions[1] = 'b'\n\$answer = 0", '0'],
            'numfunc'      => ['numfunc',      "\$variables = \"x\"\n\$answer = \"x^2\"", 'x^2'],
            'calcinterval' => ['calcinterval', "\$answer = \"[2,5)\"",                '[2,5)'],
            'string'       => ['string',       "\$answer = \"hello\"",                'hello'],
        ];
    }

    #[DataProvider('scoreCases')]
    public function test_correct_answer_scores_full(string $qtype, string $control, string $answer): void
    {
        $result = $this->service()->score(ScoreRequest::fromArray([
            'qtype' => $qtype,
            'control' => $control,
            'seed' => self::SEED,
            'answers' => [['id' => 'qn0', 'value' => $answer]],
        ]));

        self::assertEqualsWithDelta(1.0, $result->scores[0] ?? 0, 0.01, "qtype=$qtype did not score 1.0 for a correct answer");
    }
}
