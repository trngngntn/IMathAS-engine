<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

final class ScoreResult
{
    public function __construct(
        public readonly array $scores,
        public readonly array $raw,
        public readonly array $answeights,
        public readonly bool $allAnswered,
    ) {
    }

    public function toArray(): array
    {
        return [
            'scores' => $this->scores,
            'raw' => $this->raw,
            'answeights' => $this->answeights,
            'allAnswered' => $this->allAnswered,
        ];
    }
}
