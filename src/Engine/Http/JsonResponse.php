<?php

declare(strict_types=1);

namespace IMathAS\Engine\Http;

final class JsonResponse
{
    /** @var callable(int,string):void */
    private $emit;

    /**
     * @param (callable(int,string):void)|null $emit Status+body emitter; defaults to real HTTP output.
     */
    public function __construct(?callable $emit = null)
    {
        $this->emit = $emit ?? static function (int $status, string $body): void {
            http_response_code($status);
            header('Content-Type: application/json');
            echo $body;
        };
    }

    public function success(array $data, array $errors = []): void
    {
        ($this->emit)(200, (string) json_encode(
            ['ok' => true, 'data' => $data, 'errors' => array_values($errors)],
            JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));
    }

    public function failure(string $code, string $message, int $status): void
    {
        ($this->emit)($status, (string) json_encode(
            ['ok' => false, 'error' => ['code' => $code, 'message' => $message]],
            JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));
    }
}
