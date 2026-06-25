<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

final class ScoreResult
{
    /**
     * @param list<array{id: string, raw: float, weight: float, score: float}> $parts
     *   One entry per answer part, addressed by its input id (`qn0`, `qn1`, …):
     *   - `raw`    per-part correctness (1 right, 0 wrong, fractional partial)
     *   - `weight` the part's weight toward the whole question
     *   - `score`  weighted, normalized contribution (`sum(score)` = overall, 0..1)
     * @param list<string> $errors
     */
    public function __construct(
        public readonly array $parts,
        public readonly bool $allAnswered,
        public readonly array $errors = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'parts' => $this->parts,
            'allAnswered' => $this->allAnswered,
        ];
    }
}
