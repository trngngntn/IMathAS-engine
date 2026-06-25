<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

use IMathAS\Engine\EngineException;

/**
 * One submitted answer field: the input `id` from the rendered question HTML
 * and its student-entered `value`. The id is the engine input name (`qn0` for
 * a single-part question; `qn0`, `qn1`, … for the parts of a multipart one,
 * plus sub-field forms like `qn1-0` / `qn1-val` / `qs1`). Both single and
 * multipart questions use this same flat shape — the consumer echoes back the
 * input names it received from `/render`.
 */
final class Answer
{
    /** Engine input-name forms: qn / qs + digits, optional `-<idx>|-val|-choicemap`. */
    private const string ID_PATTERN = '/^q[ns]\d+(-(\d+|val|choicemap))?$/';

    public function __construct(
        public readonly string $id,
        public readonly string $value,
    ) {
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !isset($data['id']) || !is_string($data['id'])) {
            throw new EngineException('invalid_request', 'Each answer requires a string "id"');
        }
        if (preg_match(self::ID_PATTERN, $data['id']) !== 1) {
            throw new EngineException('invalid_request', "Invalid answer id: {$data['id']}");
        }
        if (!array_key_exists('value', $data) || !is_string($data['value'])) {
            throw new EngineException('invalid_request', "Answer \"{$data['id']}\" requires a string \"value\"");
        }

        return new self($data['id'], $data['value']);
    }
}
