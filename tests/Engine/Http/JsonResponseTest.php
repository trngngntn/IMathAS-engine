<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Http;

use IMathAS\Engine\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function test_success_emits_envelope(): void
    {
        $captured = [];
        $response = new JsonResponse(static function (int $status, string $body) use (&$captured): void {
            $captured = ['status' => $status, 'body' => $body];
        });

        $response->success(['x' => 1]);

        self::assertSame(200, $captured['status']);
        self::assertSame(
            ['ok' => true, 'data' => ['x' => 1], 'errors' => [], 'diagnostics' => []],
            json_decode($captured['body'], true),
        );
    }

    public function test_success_includes_diagnostics(): void
    {
        $captured = [];
        $response = new JsonResponse(static function (int $status, string $body) use (&$captured): void {
            $captured = ['status' => $status, 'body' => $body];
        });

        $diag = [['level' => 'Warning', 'message' => 'Undefined array key "x"', 'file' => '/a.php', 'line' => 9, 'count' => 2]];
        $response->success(['q' => 1], [], $diag);

        self::assertSame(
            ['ok' => true, 'data' => ['q' => 1], 'errors' => [], 'diagnostics' => $diag],
            json_decode($captured['body'], true),
        );
    }

    public function test_success_errors_emitted_as_list(): void
    {
        $captured = [];
        $response = new JsonResponse(static function (int $status, string $body) use (&$captured): void {
            $captured = ['status' => $status, 'body' => $body];
        });

        $response->success([], ['a' => 'msg']);

        self::assertSame(200, $captured['status']);
        self::assertSame(
            ['ok' => true, 'data' => [], 'errors' => ['msg'], 'diagnostics' => []],
            json_decode($captured['body'], true),
        );
    }

    public function test_failure_emits_envelope(): void
    {
        $captured = [];
        $response = new JsonResponse(static function (int $status, string $body) use (&$captured): void {
            $captured = ['status' => $status, 'body' => $body];
        });

        $response->failure('invalid_request', 'bad', 400);

        self::assertSame(400, $captured['status']);
        self::assertSame(
            ['ok' => false, 'error' => ['code' => 'invalid_request', 'message' => 'bad'], 'diagnostics' => []],
            json_decode($captured['body'], true),
        );
    }
}
