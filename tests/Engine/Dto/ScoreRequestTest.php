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
            'answers' => [['id' => 'qn0', 'value' => '12']],
            'partsToScore' => [0],
        ]);

        self::assertSame('number', $req->qtype);
        self::assertSame('$answer = 12', $req->control);
        self::assertSame(7, $req->seed);
        self::assertCount(1, $req->answers);
        self::assertSame('qn0', $req->answers[0]->id);
        self::assertSame('12', $req->answers[0]->value);
        self::assertSame([0], $req->partsToScore);
    }

    public function test_builds_multipart_answers(): void
    {
        $req = ScoreRequest::fromArray([
            'qtype' => 'multipart',
            'control' => '$anstypes = array("number","number")',
            'seed' => 7,
            'answers' => [
                ['id' => 'qn0', 'value' => '3'],
                ['id' => 'qn1', 'value' => '4'],
            ],
        ]);

        self::assertCount(2, $req->answers);
        self::assertSame('qn1', $req->answers[1]->id);
        self::assertSame('4', $req->answers[1]->value);
    }

    public function test_parts_to_score_defaults_to_null(): void
    {
        $req = ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => '$answer = 12',
            'seed' => 7,
            'answers' => [['id' => 'qn0', 'value' => '12']],
        ]);

        self::assertNull($req->partsToScore);
    }

    public function test_missing_qtype_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['control' => '$answer=12', 'seed' => 7, 'answers' => [['id' => 'qn0', 'value' => '12']]]);
    }

    public function test_missing_control_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['qtype' => 'number', 'seed' => 7, 'answers' => [['id' => 'qn0', 'value' => '12']]]);
    }

    public function test_missing_answers_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['qtype' => 'number', 'control' => '$answer=12', 'seed' => 7]);
    }

    public function test_empty_answers_throws_invalid_request(): void
    {
        $this->assertInvalidRequest(['qtype' => 'number', 'control' => '$answer=12', 'seed' => 7, 'answers' => []]);
    }

    public function test_answer_missing_id_throws_invalid_request(): void
    {
        $this->assertInvalidRequest([
            'qtype' => 'number', 'control' => '$answer=12', 'seed' => 7,
            'answers' => [['value' => '12']],
        ]);
    }

    public function test_answer_bad_id_throws_invalid_request(): void
    {
        $this->assertInvalidRequest([
            'qtype' => 'number', 'control' => '$answer=12', 'seed' => 7,
            'answers' => [['id' => 'GLOBALS', 'value' => 'x']],
        ]);
    }

    public function test_missing_seed_throws_invalid_request(): void
    {
        $this->assertInvalidRequest([
            'qtype' => 'number', 'control' => '$answer=12',
            'answers' => [['id' => 'qn0', 'value' => '12']],
        ]);
    }

    public function test_non_numeric_seed_throws_invalid_request(): void
    {
        $this->assertInvalidRequest([
            'qtype' => 'number',
            'control' => '$answer=12',
            'answers' => [['id' => 'qn0', 'value' => '12']],
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
