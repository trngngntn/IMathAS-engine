<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Dto;

use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\EngineException;
use PHPUnit\Framework\TestCase;

final class ScoreRequestTest extends TestCase
{
    public function test_builds_from_full_payload(): void
    {
        $req = ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => '$answer = 12',
            'seed' => 7,
            'answer' => '12',
            'partsToScore' => [0],
        ]);

        self::assertSame('number', $req->qtype);
        self::assertSame('$answer = 12', $req->control);
        self::assertSame(7, $req->seed);
        self::assertSame('12', $req->answer);
        self::assertSame([0], $req->partsToScore);
    }

    public function test_parts_to_score_defaults_to_null(): void
    {
        $req = ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => '$answer = 12',
            'seed' => 7,
            'answer' => '12',
        ]);

        self::assertNull($req->partsToScore);
    }

    public function test_missing_qtype_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['control' => '$answer=12', 'seed' => 7, 'answer' => '12']);
    }

    public function test_missing_control_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['qtype' => 'number', 'seed' => 7, 'answer' => '12']);
    }

    public function test_missing_answer_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['qtype' => 'number', 'control' => '$answer=12', 'seed' => 7]);
    }

    public function test_missing_seed_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['qtype' => 'number', 'control' => '$answer=12', 'answer' => '12']);
    }

    public function test_non_numeric_seed_throws_invalid_request(): void
    {
        $this->assertInvalidRequest([
            'qtype' => 'number',
            'control' => '$answer=12',
            'answer' => '12',
            'seed' => 'abc',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertInvalidRequest(array $data): void
    {
        try {
            ScoreRequest::fromArray($data);
            self::fail('Expected EngineException was not thrown');
        } catch (EngineException $e) {
            self::assertSame('invalid_request', $e->errorCode);
        }
    }
}
