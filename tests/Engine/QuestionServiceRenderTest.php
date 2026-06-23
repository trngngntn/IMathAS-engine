<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\RenderRequest;
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
    }
}
