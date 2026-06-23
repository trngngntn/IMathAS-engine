<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Http;

use IMathAS\Engine\EngineException;
use IMathAS\Engine\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class JsonRequestTest extends TestCase
{
    public function test_require_post_rejects_get(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Method Not Allowed');
        JsonRequest::requirePost('GET');
    }

    public function test_require_post_allows_post(): void
    {
        JsonRequest::requirePost('POST');
        $this->expectNotToPerformAssertions();
    }

    public function test_parse_json_body_returns_array(): void
    {
        self::assertSame(['a' => 1], JsonRequest::parseJsonBody('{"a":1}'));
    }

    public function test_parse_json_body_rejects_non_object(): void
    {
        $this->expectException(EngineException::class);
        JsonRequest::parseJsonBody('not json');
    }
}
