<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

use IMathAS\Engine\EngineException;

final class ScoreRequest
{
    /**
     * @param list<Answer> $answers   submitted fields, keyed by engine input id
     * @param ?list<int>   $partsToScore  part indices to grade, or null for all
     */
    public function __construct(
        public readonly string $qtype,
        public readonly string $control,
        public readonly int $seed,
        public readonly array $answers,
        public readonly ?array $partsToScore,
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['qtype', 'control'] as $required) {
            if (!isset($data[$required]) || !is_string($data[$required]) || $data[$required] === '') {
                throw new EngineException('invalid_request', "Missing or empty required field: {$required}");
            }
        }
        if (!isset($data['seed']) || !is_numeric($data['seed'])) {
            throw new EngineException('invalid_request', 'Missing or non-numeric required field: seed');
        }
        if (!isset($data['answers']) || !is_array($data['answers']) || $data['answers'] === []) {
            throw new EngineException('invalid_request', 'Missing or empty required field: answers');
        }

        $answers = [];
        foreach ($data['answers'] as $entry) {
            $answers[] = Answer::fromArray($entry);
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
            answers: $answers,
            partsToScore: $parts,
        );
    }
}
