<?php

declare(strict_types=1);

namespace IMathAS\assess2\questions;

/**
 * The single definition of how a multipart question's individual parts are
 * addressed in `$_POST` keys and answer-box ids (e.g. `qn{ref}`).
 *
 * Each part of a question is named by an integer *reference*. A question owns a
 * contiguous block of {@see self::STRIDE} references, so part `p` of question
 * `qn` packs to a single number that the renderer emits as the input name and
 * the score engine reads back.
 *
 * This packing used to be copy-pasted as `($qn + 1) * 1000 + $partnum` (and its
 * `% 1000` / `floor(.. / 1000)` inverses) across every answer box, every score
 * part, and the score/generate orchestrators. It now lives here once: change
 * the scheme in this class and every caller follows.
 */
final class PartRef
{
    /** References per question. Question `qn` owns `[pack(qn,0), pack(qn,STRIDE))`. */
    public const int STRIDE = 1000;

    /** Reference for part `$part` (0-based) of question `$qn` (0-based). */
    public static function pack(int $qn, int $part): int
    {
        return ($qn + 1) * self::STRIDE + $part;
    }

    /** The 0-based question a reference belongs to. */
    public static function questionOf(int $ref): int
    {
        return intdiv($ref, self::STRIDE) - 1;
    }

    /** The 0-based part index within its question. */
    public static function partOf(int $ref): int
    {
        return $ref % self::STRIDE;
    }

    /** Whether `$ref` names a part of question `$qn` (0-based). */
    public static function belongsTo(int $ref, int $qn): bool
    {
        return self::questionOf($ref) === $qn;
    }
}
