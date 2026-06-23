<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Http;

use IMathAS\Engine\EngineException;
use IMathAS\Engine\Http\JsonRequest;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->expectNotToPerformAssertions();
        JsonRequest::requirePost('POST');
    }

    public function test_parse_json_body_returns_array(): void
    {
        self::assertSame(['a' => 1], JsonRequest::parseJsonBody('{"a":1}'));
    }

    #[DataProvider('nonObjectJsonProvider')]
    public function test_parse_json_body_rejects_non_object(string $raw): void
    {
        try {
            JsonRequest::parseJsonBody($raw);
            self::fail('Expected EngineException to be thrown for: ' . $raw);
        } catch (EngineException $e) {
            self::assertSame('invalid_request', $e->errorCode);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonObjectJsonProvider(): array
    {
        return [
            'invalid json' => ['not json'],
            'json string' => ['"string"'],
            'json number' => ['42'],
            'json null' => ['null'],
            'json array' => ['[1,2]'],
        ];
    }
}
