<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Dto;

use IMathAS\Engine\Dto\RenderResult;
use PHPUnit\Framework\TestCase;

final class RenderResultTest extends TestCase
{
    public function test_to_array_round_trip(): void
    {
        $result = new RenderResult(
            seed: 42,
            question: 'What is $a?',
            solution: 'because',
            vars: ['a' => 5],
            answers: ['5'],
            jsparams: ['foo' => 'bar'],
        );

        self::assertSame([
            'seed' => 42,
            'question' => 'What is $a?',
            'solution' => 'because',
            'vars' => ['a' => 5],
            'answers' => ['5'],
            'jsparams' => ['foo' => 'bar'],
        ], $result->toArray());
    }
}
