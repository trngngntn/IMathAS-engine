<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

use IMathAS\Engine\EngineException;

final class RenderRequest
{
    public function __construct(
        public readonly string $qtype,
        public readonly string $control,
        public readonly string $qtext,
        public readonly string $solution,
        public readonly int $seed,
        public readonly Stype $stype,
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['qtype', 'control', 'qtext'] as $required) {
            if (!isset($data[$required]) || !is_string($data[$required]) || $data[$required] === '') {
                throw new EngineException('invalid_request', "Missing or empty required field: {$required}");
            }
        }

        $seed = isset($data['seed']) ? (int) $data['seed'] : random_int(0, 10000);

        return new self(
            qtype: $data['qtype'],
            control: $data['control'],
            qtext: $data['qtext'],
            solution: isset($data['solution']) && is_string($data['solution']) ? $data['solution'] : '',
            seed: $seed,
            stype: Stype::fromString($data['stype'] ?? null),
        );
    }
}
