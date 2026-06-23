<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

use IMathAS\Engine\EngineException;

final class ScoreRequest
{
    public function __construct(
        public readonly string $qtype,
        public readonly string $control,
        public readonly int $seed,
        public readonly string $answer,
        public readonly ?array $partsToScore,
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['qtype', 'control', 'answer'] as $required) {
            if (!isset($data[$required]) || !is_string($data[$required]) || $data[$required] === '') {
                throw new EngineException('invalid_request', "Missing or empty required field: {$required}");
            }
        }
        if (!isset($data['seed']) || !is_numeric($data['seed'])) {
            throw new EngineException('invalid_request', 'Missing or non-numeric required field: seed');
        }

        $parts = null;
        if (isset($data['partsToScore'])) {
            $raw = $data['partsToScore'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }
            if (is_array($raw)) {
                $parts = array_map('intval', array_values($raw));
            }
        }

        return new self(
            qtype: $data['qtype'],
            control: $data['control'],
            seed: (int) $data['seed'],
            answer: $data['answer'],
            partsToScore: $parts,
        );
    }
}
