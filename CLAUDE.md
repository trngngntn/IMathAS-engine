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

`Bootstrap::init()` replaces the old `init.php → validate.php → config.php` chain:
it defines the constants/globals the engine reads, sets `$_SESSION` render prefs
(`graphdisp`/`drawentry` = 1, which also keep the DB branch unreachable), and
includes `includes/sanitize.php`. No auth, no DB, no LMS. The engine's `_()`
localization calls resolve to the gettext built-in (pass-through), with a
fallback defined in `config.php`; no translation catalogs are shipped.

## API contract

Success → HTTP 200 `{ "ok": true, "data": {...}, "errors": [...] }` (engine domain
errors, e.g. eval issues, ride along in `errors`). Bad input → 400, wrong method →
405, both `{ "ok": false, "error": { "code", "message" } }`.

- **render** `data`: `{ seed, question, solution, vars, answers, jsparams }`
- **score** `data`: `{ scores, raw, answeights, allAnswered }`

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
- `includes/` was pruned to only what the engine transitively needs: `sanitize`,
  `htmLawed`, `filehandler`, `Rand`. (`filehandler` stays because `htmLawed` —
  loaded on every request via `sanitize` — calls it for inline `data:image`
  handling; most of its other functions are dead but harmless, gated behind an
  `$AWSkey`/`filehandlertype=='s3'` that this engine never sets.)
- **No file uploads.** The `file` answer type (`FileScorePart`/`FileUploadAnswerBox`),
  `includes/S3.php`, and `includes/svg-sanitizer/` were removed. A `file` qtype now
  returns a clean "Unknown answer type" engine error, not a fatal. Consumers handle
  any file handling externally.
- **Graphs render client-side only.** The server-side SVG→PNG rasterizer
  (`filter/graph/asciisvgimg.php`) and its `graphdisp==2` code paths in
  `filter/filter.php` were removed. `showplot`/`showasciisvg` emit client-side
  `<embed>`/draw commands (graphdisp=1); the consumer renders them. No `gd`
  extension or `assessment/font/` needed.
- `error_reporting` in `config.php` suppresses legacy notice/deprecation/warning
  noise so it never leaks into JSON responses — keep it.
- Hook points the engine still honors (via `$CFG['hooks']`): `assess2/assess_standalone`,
  `assess2/questions/score_engine`, `assess2/questions/question_html_generator`,
  and the choices/multiple-answer scorepart hooks.

## Verifying changes

Always run both after engine-adjacent changes:
`docker compose exec php vendor/bin/phpunit` and `./scripts/smoke.sh`. The suite
covers render + score (correct/wrong) against the real engine; the smoke script
gates the live endpoints. For broad changes, render across many `qtype`s
(number, calculated, choices, multans, matching, numfunc, matrix, interval,
ntuple, string, essay, draw, multipart, conditional) — that is how the strip was
verified safe.
