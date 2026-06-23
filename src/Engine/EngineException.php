<?php

declare(strict_types=1);

namespace IMathAS\Engine;

use RuntimeException;

final class EngineException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
