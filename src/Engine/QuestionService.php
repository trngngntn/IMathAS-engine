<?php

declare(strict_types=1);

namespace IMathAS\Engine;

use AssessStandalone;
use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\Dto\RenderResult;
use IMathAS\Engine\Dto\Stype;
use PDO;

/**
 * Clean facade over the IMathAS AssessStandalone engine for the standalone API.
 * Owns question-data assembly, state construction, and result mapping.
 */
final class QuestionService
{
    /** Arbitrary but consistent engine question slot index. */
    public const int QUESTION_SLOT = 27;

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
            'solutionopts' => 6,
            'deleted' => 0,
            'hasimg' => 0,
            'license' => 1,
            'isrand' => 1,
            'a11yalttype' => 0,
            'a11yalt' => 0,
        ];
    }
}
