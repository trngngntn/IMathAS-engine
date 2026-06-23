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

    public function test_missing_required_field_throws_invalid_request(): void
    {
        $this->expectException(EngineException::class);
        RenderRequest::fromArray(['qtype' => 'number', 'control' => '$a=5']); // no qtext
    }
}
