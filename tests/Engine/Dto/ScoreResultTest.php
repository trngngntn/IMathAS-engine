<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Dto;

use IMathAS\Engine\Dto\ScoreResult;
use PHPUnit\Framework\TestCase;

final class ScoreResultTest extends TestCase
{
    public function test_to_array_round_trip(): void
    {
        $result = new ScoreResult(
            scores: [1.0],
            raw: ['12'],
            answeights: [1],
            allAnswered: true,
        );

        self::assertSame([
            'scores' => [1.0],
            'raw' => ['12'],
            'answeights' => [1],
            'allAnswered' => true,
        ], $result->toArray());
    }
}
