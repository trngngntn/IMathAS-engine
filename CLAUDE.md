# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this is

A **DB-less, API-only question engine** carved out of IMathAS. It does exactly two
things over HTTP: **render** an algorithmically-generated math question, and
**grade** a student answer. No LMS, no MySQL, no frontend, no authoring tools.

The upstream full IMathAS (LMS, gradebook, forums, LTI, install/migrations,
client assets) has been stripped out. What remains is the assessment engine
(`assess2/`, `assessment/`) plus a thin, clean service layer (`src/Engine/`).

## Requirements & run

PHP 8.4. Everything runs in Docker (`php:8.4-fpm` + nginx, **no database**):

```bash
docker compose up -d --build            # build + start, serves on http://localhost:8088
docker compose exec php composer install
docker compose exec php vendor/bin/phpunit   # full test suite (auto-discovers phpunit.xml.dist)
./scripts/smoke.sh                       # live end-to-end: render + score + 405 guards
```

There is no PHP on the host — run all `composer`/`phpunit`/`php` commands via
`docker compose exec php ...`.

## Architecture

Thin endpoints → service layer → legacy engine.

| Layer | Files |
|-------|-------|
| Endpoints | `problems.php` (render, **JSON** body), `scores.php` (grade, **form-encoded** body) |
| Service (`IMathAS\Engine\`, PSR-4 → `src/Engine/`) | `Bootstrap` (DB-less init), `QuestionService` (wraps `AssessStandalone`), `Dto/*` (readonly DTOs + `Stype` enum), `Http/{JsonRequest,JsonResponse}`, `EngineException` |
| Engine (kept as-is) | `assess2/AssessStandalone.php`, `assess2/questions/*`, `assessment/{macros,interpret5,mathparser,mathphp2}.php` + `assessment/macros/*`, `assessment/libs/*` (loaded dynamically via `loadlibrary`) |

### DB-less design (important)

Question data is **injected** via `AssessStandalone::setQuestionData()`, never loaded
from a DB. The only `PDO` is a throwaway `new PDO('sqlite::memory:')` created in
`Bootstrap` solely to satisfy the engine's non-nullable `PDO` type hints — it is
**never queried**. The lone `SELECT` (`loadQuestionData`) sits behind an a11y-alt
branch that `freshState()`/`defaultQuestionData()` keep unreachable.

If you change the render/score path, verify no DB query is introduced (a GuardPDO
that throws on `prepare`/`query`/`exec` is the way this was proven).

`Bootstrap::init()` replaces the old `init.php → validate.php → config.php` chain
and is the engine's **sole config surface** — there is no separate config file.
It defines the constants/globals the engine reads (`imasroot`/`staticroot` = `''`,
i.e. served at web root; `DBH`, `myrights`, `useeqnhelper`), sets `$_SESSION`
render prefs (`graphdisp`/`drawentry` = 1, which also keep the DB branch
unreachable), and includes `includes/sanitize.php`. No auth, no DB, no LMS. The
engine's `_()` localization calls resolve to the gettext built-in (pass-through),
with a fallback `_()` defined in `Bootstrap`; no translation catalogs are shipped.

## API contract

Success → HTTP 200 `{ "ok": true, "data": {...}, "errors": [...], "diagnostics": [...] }`.
Bad input → 400, wrong method → 405, both
`{ "ok": false, "error": { "code", "message" }, "diagnostics": [...] }`.

- **render** `data`: `{ seed, question, solution, vars, answers, jsparams }`
- **score** `data`: `{ scores, raw, answeights, allAnswered }`
- **`errors`** — engine domain errors (bad question code, warnings raised while
  the engine evals it), as messages.
- **`diagnostics`** — PHP warnings/notices/deprecations captured *outside* the
  engine's own (scoped) eval error handler, via `IMathAS\Engine\Diagnostics`
  (installed in the endpoints). Deduplicated `{ level, message, file, line, count }`.
  This replaces the old blanket `error_reporting` suppression — noise is now
  surfaced per request, never swallowed, and `display_errors` is forced off so it
  can't leak into the JSON body.

The author-supplied `control` contains the full setup **including** the `$answer`
assignment. `vars` keys are `$`-prefixed (e.g. `{"$a":5}`). `stype` is `template`
(engine solution) or `code` (eval author solution against generated vars, in an
isolated scope).

## Conventions & gotchas

- New code uses PHP 8.4 idioms: `readonly` promoted constructors, enums, typed
  signatures. Keep the engine (`assess2/`, `assessment/`) untouched unless
  necessary.
- **Engine version note:** this repo runs an *older* `assess2`/`assessment` than
  upstream MyOpenMath. A minimal vars accessor was backported (`genVarsOutput` in
  `assessment/interpret5.php`; `Question::get/setVarsOutput`; capture in
  `QuestionHtmlGenerator`). `genVarsOutput` emits `$hidepreview=1` — this is
  verbatim from upstream and intentionally kept (preview markup is irrelevant to
  an API consumer).
- `includes/` is down to two files: `sanitize.php` (input/output sanitization
  helpers — `Sanitize::encodeStringForDisplay`, `generateAttributeString`, etc.,
  used throughout) and `Rand.php` (seeded RNG). Adding an engine path that needs
  another `includes/` file means restoring it.
- **No `file` or `essay` answer types.** Both were removed (`FileScorePart`/
  `FileUploadAnswerBox`/`EssayScorePart`/`EssayAnswerBox`), and with them the entire
  HTML-sanitizer/file-storage chain they were the only consumers of: `htmLawed.php`,
  `filehandler.php`, `S3.php`, `svg-sanitizer/`. (htmLawed's `myhtmLawed` was called
  only when grading an essay answer; filehandler/S3 only on file upload.) A `file` or
  `essay` qtype now returns a clean "Unknown answer type" engine error (HTTP 200, in
  `errors`), not a fatal. Consumers handle any rich-text/file input externally.
- **Graphs render client-side only.** `graphdisp` is hardcoded to 1, so both
  server-side graph paths were removed from `filter/` entirely (the whole
  `filter/graph/` dir is gone): the SVG→PNG rasterizer (`asciisvgimg.php`,
  `graphdisp==2`) and the text-alternative fallback (`sscrtotext.php`,
  `graphdisp==0`), along with their code paths in `filter/filter.php`.
  `showplot`/`showasciisvg` emit client-side `<embed>`/draw commands; the
  consumer renders them (and owns graph accessibility). No `gd` extension or
  `assessment/font/` needed. `filter/` is now just `filter.php` +
  `math/ASCIIMath2TeX.php`.
- `IMathAS\Engine\Diagnostics` (installed by the endpoints) sets `error_reporting(E_ALL)`
  + `display_errors=0` and captures PHP notices/warnings/deprecations into the
  response `diagnostics` field — they are surfaced, not suppressed or leaked.
- Hook points the engine still honors (via `$GLOBALS['CFG']['hooks']`, unset by
  default so all are no-ops): `assess2/assess_standalone`,
  `assess2/questions/score_engine`, `assess2/questions/question_html_generator`,
  and the choices/multiple-answer scorepart hooks.

## Verifying changes

Always run both after engine-adjacent changes:
`docker compose exec php vendor/bin/phpunit` and `./scripts/smoke.sh`. The suite
covers render + score (correct/wrong) against the real engine; the smoke script
gates the live endpoints. For broad changes, render across many `qtype`s
(number, calculated, choices, multans, matching, numfunc, matrix, interval,
ntuple, string, draw, multipart, conditional) — that is how the strip was
verified safe.
