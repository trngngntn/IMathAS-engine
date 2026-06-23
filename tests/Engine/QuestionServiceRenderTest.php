<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\Dto\Stype;
use IMathAS\Engine\QuestionService;
use PHPUnit\Framework\TestCase;

final class QuestionServiceRenderTest extends TestCase
{
    private function service(): QuestionService
    {
        Bootstrap::init();
        require_once dirname(__DIR__, 2) . '/assess2/AssessStandalone.php';
        return new QuestionService($GLOBALS['DBH']);
    }

    public function test_renders_a_simple_number_question(): void
    {
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$b = 7\n\$answer = \$a + \$b",
            'qtext' => 'Find $a + $b',
            'seed' => 1234,
        ]);

        $result = $this->service()->render($req);

        self::assertSame(1234, $result->seed);
        self::assertStringContainsString('Find 5 + 7', $result->question);
        self::assertNotEmpty($result->answers);
        // The correct answer (12) is surfaced for the single part.
        self::assertStringContainsString('12', implode(' ', array_map('strval', (array) $result->answers)));

        // Generated template vars are surfaced; keys keep their leading '$'
        // and the engine returns native int values for numeric assignments.
        self::assertSame(5, $result->vars['$a']);
        self::assertSame(7, $result->vars['$b']);
        self::assertSame(12, $result->vars['$answer']);
    }

    public function test_stype_code_solution_evaluates_against_generated_vars(): void
    {
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$b = 7\n\$answer = \$a + \$b",
            'qtext' => 'Find $a + $b',
            'solution' => 'echo "ans=$answer";',
            'stype' => 'code',
            'seed' => 1234,
        ]);

        $result = $this->service()->render($req);

        self::assertSame(Stype::Code, $req->stype);
        self::assertStringContainsString('ans=12', $result->solution);
    }

    public function test_stype_code_author_var_named_code_does_not_clobber_solution(): void
    {
        // Author control defines a var named $code, which previously collided
        // with evalSolutionCode()'s local $code before eval ran. The isolated
        // closure must keep the solution code intact -> renders ok=5, not 999.
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$code = 999\n\$answer = \$a",
            'qtext' => 'Find $a',
            'solution' => 'echo "ok=$answer";',
            'stype' => 'code',
            'seed' => 1234,
        ]);

        $result = $this->service()->render($req);

        self::assertStringContainsString('ok=5', $result->solution);
        self::assertStringNotContainsString('999', $result->solution);
    }
}
