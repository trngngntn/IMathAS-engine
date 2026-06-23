<?php

declare(strict_types=1);

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\EngineException;
use IMathAS\Engine\Http\JsonRequest;
use IMathAS\Engine\Http\JsonResponse;
use IMathAS\Engine\QuestionService;

require_once __DIR__ . '/vendor/autoload.php';

Bootstrap::init();
require_once __DIR__ . '/assess2/AssessStandalone.php';

$response = new JsonResponse();

try {
    JsonRequest::requirePost($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $result = (new QuestionService($GLOBALS['DBH']))->score(ScoreRequest::fromArray($_POST));
    $response->success($result->toArray(), $result->errors);
} catch (EngineException $e) {
    $status = $e->errorCode === 'method_not_allowed' ? 405 : 400;
    $response->failure($e->errorCode, $e->getMessage(), $status);
}
