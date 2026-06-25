<?php

declare(strict_types=1);

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Diagnostics;
use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\EngineException;
use IMathAS\Engine\Http\JsonRequest;
use IMathAS\Engine\Http\JsonResponse;
use IMathAS\Engine\QuestionService;

require_once __DIR__ . '/src/Engine/autoload.php';

// Capture PHP warnings/notices/deprecations for this request (returned in the
// response `diagnostics` field). Install before anything else runs.
Diagnostics::install();

Bootstrap::init();
require_once __DIR__ . '/assess2/AssessStandalone.php';

$response = new JsonResponse();

try {
    JsonRequest::requirePost($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $payload = JsonRequest::parseJsonBody(file_get_contents('php://input') ?: '');
    $result = (new QuestionService($GLOBALS['DBH']))->render(RenderRequest::fromArray($payload));
    $response->success($result->toArray(), $result->errors, Diagnostics::drain());
} catch (EngineException $e) {
    $status = $e->errorCode === 'method_not_allowed' ? 405 : 400;
    $response->failure($e->errorCode, $e->getMessage(), $status, Diagnostics::drain());
}
