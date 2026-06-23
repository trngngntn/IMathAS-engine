<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

final class RenderResult
{
    public function __construct(
        public readonly int $seed,
        public readonly string $question,
        public readonly string $solution,
        public readonly array $vars,
        public readonly array $answers,
        public readonly array $jsparams,
        public readonly array $errors = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'seed' => $this->seed,
            'question' => $this->question,
            'solution' => $this->solution,
            'vars' => $this->vars,
            'answers' => $this->answers,
            'jsparams' => $this->jsparams,
        ];
    }
}
