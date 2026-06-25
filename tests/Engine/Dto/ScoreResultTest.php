<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Dto;

use IMathAS\Engine\Dto\ScoreResult;
use PHPUnit\Framework\TestCase;

final class ScoreResultTest extends TestCase
{
    public function test_to_array_round_trip(): void
    {
        $parts = [
            ['id' => 'qn0', 'raw' => 1.0, 'weight' => 1.0, 'score' => 0.5],
            ['id' => 'qn1', 'raw' => 0.0, 'weight' => 1.0, 'score' => 0.0],
        ];
        $result = new ScoreResult(parts: $parts, allAnswered: true);

        self::assertSame([
            'parts' => $parts,
            'allAnswered' => true,
        ], $result->toArray());
    }
}
