<?php

declare(strict_types=1);

namespace IMathAS\Engine\Http;

use IMathAS\Engine\EngineException;

final class JsonRequest
{
    public static function requirePost(string $method): void
    {
        if (strtoupper($method) !== 'POST') {
            throw new EngineException('method_not_allowed', 'Method Not Allowed');
        }
    }

    public static function parseJsonBody(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            throw new EngineException('invalid_request', 'Request body is not a valid JSON object');
        }
        return $data;
    }
}
