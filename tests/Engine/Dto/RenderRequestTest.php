<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Dto;

use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\Dto\Stype;
use IMathAS\Engine\EngineException;
use PHPUnit\Framework\TestCase;

final class RenderRequestTest extends TestCase
{
    public function test_builds_from_full_payload(): void
    {
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => '$a = 5',
            'qtext' => 'Find $a',
            'solution' => 'because',
            'seed' => 42,
            'stype' => 'code',
        ]);

        self::assertSame('number', $req->qtype);
        self::assertSame('$a = 5', $req->control);
        self::assertSame('Find $a', $req->qtext);
        self::assertSame('because', $req->solution);
        self::assertSame(42, $req->seed);
        self::assertSame(Stype::Code, $req->stype);
    }

    public function test_defaults_solution_stype_and_random_seed(): void
    {
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => '$a = 5',
            'qtext' => 'Find $a',
        ]);

        self::assertSame('', $req->solution);
        self::assertSame(Stype::Template, $req->stype);
        self::assertGreaterThanOrEqual(0, $req->seed);
    }

    public function test_missing_qtype_throws_invalid_request(): void
    {
        $this->assertMissingFieldThrows(['control' => '$a=5', 'qtext' => 'Find $a']);
    }

    public function test_missing_control_throws_invalid_request(): void
    {
        $this->assertMissingFieldThrows(['qtype' => 'number', 'qtext' => 'Find $a']);
    }

    public function test_missing_qtext_throws_invalid_request(): void
    {
        $this->assertMissingFieldThrows(['qtype' => 'number', 'control' => '$a=5']);
    }

    public function test_empty_qtext_throws_invalid_request(): void
    {
        $this->assertMissingFieldThrows(['qtype' => 'number', 'control' => '$a=5', 'qtext' => '']);
    }

    public function test_invalid_stype_throws_invalid_request(): void
    {
        try {
            RenderRequest::fromArray([
                'qtype' => 'number',
                'control' => '$a=5',
                'qtext' => 'Find $a',
                'stype' => 'html',
            ]);
            self::fail('Expected EngineException was not thrown');
        } catch (EngineException $e) {
            self::assertSame('invalid_request', $e->errorCode);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertMissingFieldThrows(array $data): void
    {
        try {
            RenderRequest::fromArray($data);
            self::fail('Expected EngineException was not thrown');
        } catch (EngineException $e) {
            self::assertSame('invalid_request', $e->errorCode);
        }
    }
}
