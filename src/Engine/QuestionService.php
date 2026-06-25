<?php

declare(strict_types=1);

namespace IMathAS\Engine;

use AssessStandalone;
use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\Dto\RenderResult;
use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\Dto\ScoreResult;
use IMathAS\Engine\Dto\Stype;
use IMathAS\assess2\questions\PartRef;
use PDO;

/**
 * Clean facade over the IMathAS AssessStandalone engine for the standalone API.
 * Owns question-data assembly, state construction, and result mapping.
 */
final class QuestionService
{
    /**
     * Engine question slot. Fixed at 0 so the only-question's answer ids come
     * out flat: single-part is `qn0`, and multipart parts are `qn0`, `qn1`, …
     * (see {@see \IMathAS\assess2\questions\PartRef}).
     */
    public const int QUESTION_SLOT = 0;

    public function __construct(private readonly PDO $dbh)
    {
    }

    public function render(RenderRequest $req): RenderResult
    {
        $a2 = new AssessStandalone($this->dbh);

        $qdata = $this->defaultQuestionData();
        $qdata['qtype'] = $req->qtype;
        $qdata['control'] = $req->control;
        $qdata['qtext'] = $req->qtext;
        $qdata['solution'] = $req->stype === Stype::Template ? $req->solution : '';

        $a2->setQuestionData(self::QUESTION_SLOT, $qdata);
        $a2->setState($this->freshState($req->seed));

        $disp = $a2->displayQuestion(self::QUESTION_SLOT, [
            'showans' => false,
            'showallparts' => false,
            'printformat' => true,
        ]);

        $question = $a2->getQuestion();

        if ($req->stype === Stype::Template) {
            $solution = $question->getSolutionContent();
        } else {
            $solution = $this->evalSolutionCode($req->solution, $question->getVarsOutput());
        }

        return new RenderResult(
            seed: $req->seed,
            question: $question->getQuestionContent(),
            solution: $solution,
            vars: $question->getVarsOutput(),
            answers: $question->getCorrectAnswersForParts(),
            jsparams: $disp['jsparams'] ?? [],
            errors: array_values($disp['errors'] ?? []),
        );
    }

    public function score(ScoreRequest $req): ScoreResult
    {
        $a2 = new AssessStandalone($this->dbh);

        $qdata = $this->defaultQuestionData();
        $qdata['qtype'] = $req->qtype;
        $qdata['control'] = $req->control;

        $a2->setQuestionData(self::QUESTION_SLOT, $qdata);
        $a2->setState($this->freshState($req->seed));

        $partsToScore = true;
        if ($req->partsToScore !== null) {
            $partsToScore = [];
            foreach ($req->partsToScore as $pn) {
                $partsToScore[$pn] = true;
            }
        }

        // The engine reads student answers from $_POST by input id — 'qn0' for a
        // single-part question, 'qn0','qn1',… for multipart parts (each id is the
        // input name emitted in the rendered question HTML). Snapshot any prior
        // values (deduped by id) and restore them in finally so we never leave
        // residue in the superglobal, even if scoreQuestion() throws.
        $prior = [];  // id => [bool $present, mixed $value], deduped by id
        foreach ($req->answers as $answer) {
            if (!array_key_exists($answer->id, $prior)) {
                $prior[$answer->id] = [array_key_exists($answer->id, $_POST), $_POST[$answer->id] ?? null];
            }
            $_POST[$answer->id] = $answer->value;
        }
        try {
            $result = $a2->scoreQuestion(self::QUESTION_SLOT, $partsToScore);
        } finally {
            foreach ($prior as $id => [$present, $value]) {
                if ($present) {
                    $_POST[$id] = $value;
                } else {
                    unset($_POST[$id]);
                }
            }
        }

        // Zip the engine's parallel per-part arrays into one object per part,
        // each tagged with its input id (the same id the consumer submitted /
        // /render emitted): qn0 for single-part, qn0/qn1/… for multipart.
        $scores = array_values($result['scores'] ?? []);
        $raw = array_values($result['raw'] ?? []);
        $answeights = array_values($result['answeights'] ?? []);

        $parts = [];
        foreach ($raw as $i => $partRaw) {
            $parts[] = [
                'id' => 'qn' . PartRef::pack(self::QUESTION_SLOT, $i),
                'raw' => (float) $partRaw,
                'weight' => (float) ($answeights[$i] ?? 0),
                'score' => (float) ($scores[$i] ?? 0),
            ];
        }

        return new ScoreResult(
            parts: $parts,
            allAnswered: (bool) ($result['allans'] ?? false),
            errors: array_values($result['errors'] ?? []),
        );
    }

    /**
     * Evaluate author-supplied solution PHP against generated vars (stype=code).
     * @param array<string,mixed> $vars
     */
    private function evalSolutionCode(string $code, array $vars): string
    {
        $sanitized = [];
        foreach ($vars as $key => $value) {
            $sanitized[ltrim((string) $key, '$')] = $value;
        }

        // Isolate the eval in a static closure so author-supplied vars cannot
        // clobber this method's locals (e.g. a control var named $code that
        // would otherwise overwrite the solution code before eval runs). Only
        // the vars + code reach the closure, under collision-unlikely names.
        $run = static function (array $__vars, string $__code): string {
            extract($__vars);
            ob_start();
            eval($__code);
            return (string) ob_get_clean();
        };

        return $run($sanitized, $code);
    }

    private function freshState(int $seed): array
    {
        $qn = self::QUESTION_SLOT;
        return [
            'seeds' => [$qn => $seed],
            'qsid' => [$qn => $qn],
            'stuanswers' => [],
            'stuanswersval' => [],
            'scorenonzero' => [($qn + 1) => -1],
            'scoreiscorrect' => [($qn + 1) => -1],
            'partattemptn' => [$qn => []],
            'rawscores' => [$qn => []],
        ];
    }

    /**
     * Minimal imas_questionset-shaped base record. Per-request fields
     * (qtype/control/qtext/answer/solution) are overridden by callers.
     *
     * @return array<string,mixed>
     */
    private function defaultQuestionData(): array
    {
        return [
            'id' => self::QUESTION_SLOT,
            'qtype' => 'multipart',
            'control' => '',
            'qcontrol' => '',
            'qtext' => '',
            'answer' => '',
            'solution' => '',
            'extref' => '',
            // 4 = render the detailed solution inline. (Bit 2/value 2 would add
            // a "Written Example" popup link to the removed showsoln.php; omit
            // it. Our `solution` field comes from getSolutionContent regardless.)
            'solutionopts' => 4,
            'deleted' => 0,
            'hasimg' => 0,
            'license' => 1,
            'isrand' => 1,
            'a11yalttype' => 0,
            'a11yalt' => 0,
            'lastmoddate' => 0,
        ];
    }
}
