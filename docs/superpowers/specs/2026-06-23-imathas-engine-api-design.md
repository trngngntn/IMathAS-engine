# IMathAS Question Engine — Clean API Layer (Design Spec)

**Date:** 2026-06-23
**Status:** Approved (pending spec review)

## 1. Goal

Transform this fork of IMathAS into a **DB-less, API-only question engine** exposing
exactly two capabilities:

1. **Render / generate** an algorithmic question from supplied question code.
2. **Grade / score** a student answer against that question.

The endpoints return **pure engine output** — no LaTeX conversion, no
`showplot_with_functions`/`function_list`, no prettified-solution pass. Those
GotIt-specific add-ons from the reference repo are intentionally dropped; they can
be layered on later by the consumer.

The existing assessment **engine is kept and reused** (`assess2/`, `assessment/`,
`filter/`, and the subset of `includes/` it transitively requires). We write a
clean, well-bounded layer *around* it; we do not rewrite the engine.

This spec covers **Phase 0** (the clean API layer + DB-less baseline). Stripping
the LMS, frontend assets, and DB/install infra are tracked as later phases and are
out of scope for this document beyond being enabled by it.

## 2. Constraints & Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| API contract | Free to redesign | No locked consumer; design for quality. |
| Feature scope | Pure engine only | Drop `convert_to_latex`, `showplot_with_functions`/`function_list`, prettify. |
| Architecture | Service layer + thin endpoints | Isolates engine behind one interface; unit-testable; no duplication. |
| PHP version | 8.4 | Officially supported by IMathAS per maintainer. |
| Database | None (MySQL-free) | Question data is injected via the request, not loaded from DB. |
| `$DBH` handle | Throwaway `new PDO('sqlite::memory:')` | Engine constructors require non-nullable `PDO`; in-memory SQLite satisfies the type without a server and is never queried in the inject-data flow. |
| Sessions | PHP file sessions (`$use_local_sessions`) | No DB session handler; endpoints are effectively stateless. |
| Auth | None (`validate.php` skipped) | Engine-only service; `$myrights = 0` (guest). |

## 3. Layout

```
config.php                          # DB-less engine config: $CFG, installname, error_reporting. No PDO/MySQL creds.
src/Engine/Bootstrap.php            # DB-less runtime init (see §4.1)
src/Engine/QuestionService.php      # wraps AssessStandalone; render()/score() (see §4.2)
src/Engine/Http/JsonRequest.php     # method guard + body parsing/validation
src/Engine/Http/JsonResponse.php    # emits {ok,...} envelope + HTTP status
src/Engine/Dto/RenderRequest.php    # readonly input DTO (render)
src/Engine/Dto/RenderResult.php     # readonly output DTO (render)
src/Engine/Dto/ScoreRequest.php     # readonly input DTO (score)
src/Engine/Dto/ScoreResult.php      # readonly output DTO (score)
src/Engine/Dto/Stype.php            # enum: template | code
src/Engine/EngineException.php      # typed validation/input error (-> HTTP 400)
problems.php                        # thin endpoint: bootstrap -> JsonRequest -> service->render -> JsonResponse
scores.php                          # thin endpoint: bootstrap -> JsonRequest -> service->score -> JsonResponse
```

Autoloading: add PSR-4 `"IMathAS\\Engine\\": "src/Engine/"` to `composer.json`,
then `composer dump-autoload`. Endpoints `require vendor/autoload.php`, `config.php`,
and call `Bootstrap::init()`.

All new code in `src/Engine/` is written to PHP 8.4 idioms: `readonly` promoted
constructors, enums, typed properties/returns, `match` for dispatch.

## 4. Components

### 4.1 Bootstrap (`src/Engine/Bootstrap.php`)

Replaces the `init_without_validate.php` → `init.php` → `config.php` chain for the
engine's needs only. `Bootstrap::init()`:

- Defines required constants: `MYSQL_LEFT_WRDBND` / `MYSQL_RIGHT_WRDBND` (use the
  `\b` variants, MySQL 8+), `JSON_INVALID_UTF8_IGNORE` (if missing).
- Sets globals the engine reads: `$CFG`, `$installname`, `$imasroot`, `$staticroot`,
  `$useeqnhelper = 1`, `$GLOBALS['DBH'] = new PDO('sqlite::memory:')`,
  `$GLOBALS['myrights'] = 0`.
- Seeds render prefs without a real login: `$_SESSION['userprefs']['graphdisp'] = 1`,
  `$_SESSION['userprefs']['drawentry'] = 1`, `$_SESSION['graphdisp'] = 1`.
- Includes `includes/sanitize.php` and `i18n/i18n.php`.
- Does **not**: connect to MySQL, install a DB session handler, load `validate.php`,
  or touch any LMS code.

Idempotent (guard against double-init).

### 4.2 QuestionService (`src/Engine/QuestionService.php`)

Owns the engine glue that the reference duplicated across endpoints. Constructed
with the PDO handle from Bootstrap.

- `defaultQuestionData(): array` — the minimal `imas_questionset`-shaped record the
  engine expects, with sane defaults. Overridden per request for
  `qtype`/`control`/`qtext`/`answer`/`solution`/`solutionopts`. Replaces the
  reference's inline hardcoded JSON blob.
- `render(RenderRequest $req): RenderResult` — builds question data + single-question
  `$state`, calls `AssessStandalone::displayQuestion()`, returns mapped DTO.
- `score(ScoreRequest $req): ScoreResult` — builds question data + `$state`, sets the
  student answer, calls `AssessStandalone::scoreQuestion()`, returns mapped DTO.
- The engine question slot index (reference magic `27`) is a named class constant.
- For `stype = code`, the service evaluates the supplied solution code against the
  generated vars (equivalent to the reference's non-template path) — kept because it
  is core generate behavior, not a GotIt add-on.

### 4.3 DTOs & validation

- `RenderRequest`: **required** `qtype`, `control`, `qtext`; **optional** `solution`
  (default `""`), `seed` (random int if absent), `stype` (`Stype`, default
  `template`).
- `ScoreRequest`: **required** `qtype`, `control`, `seed`, `answer`; **optional**
  `partsToScore` (array of part indices; default all).
- Construction validates presence/types; failure throws `EngineException`
  (→ HTTP 400). DTOs are `readonly`.

### 4.4 HTTP layer

- `JsonRequest`: rejects non-POST with 405; parses JSON body for render, form body
  for score; returns the decoded payload or throws `EngineException` on malformed
  input.
- `JsonResponse`: sets `Content-Type: application/json` and the status code, encodes
  the envelope with `JSON_PARTIAL_OUTPUT_ON_ERROR`.

## 5. API Contract

### Envelope

```jsonc
// success (HTTP 200) — engine domain errors (e.g. eval issues) ride along in `errors`
{ "ok": true, "data": { ... }, "errors": [ ... ] }

// failure (HTTP 400 invalid body / 405 wrong method)
{ "ok": false, "error": { "code": "invalid_request", "message": "..." } }
```

### `POST /problems.php` (render)

Request (JSON):
```jsonc
{ "qtype": "multipart", "control": "...", "qtext": "...",
  "solution": "...", "seed": 12345, "stype": "template" }
```
`data`: `{ seed, question, solution, vars, answers, jsparams }`

### `POST /scores.php` (score)

Request (form-encoded): `qtype`, `control`, `seed`, `answer` (or `qn27`-style per
part), optional `partsToScore` (JSON).
`data`: `{ scores, raw, answeights, allAnswered }`

HTTP status: 200 on success (including non-empty engine `errors`), 400 invalid
request, 405 wrong method.

## 6. Infrastructure

- **Docker:** `docker-compose.yml` with two services — `nginx` (serves, routes
  `*.php` to fpm) and `php` (`php:8.4-fpm`). **No MySQL service.**
- **`default.conf`:** nginx fastcgi config routing `.php` to `php:9000`.
- **`config.php`:** `error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING)`
  so legacy-engine notice/deprecation noise never leaks into JSON; no DB credentials.

## 7. Dependencies (must be documented in README)

The README must list everything needed to install and run:

- **Docker + Docker Compose** — brings up `php:8.4-fpm` + nginx.
- **Composer** — `composer install` (dev: Codeception) and PSR-4 autoload; at minimum
  `composer dump-autoload` for the `IMathAS\Engine\` namespace.
- **PHP 8.4 extensions:**
  - `mbstring` — **required** (engine string handling).
  - `pdo_sqlite` — required for the throwaway DB handle (bundled with PHP by default).
  - `gettext` — recommended (i18n; engine has a `_()` fallback if absent).
  - `gd` — optional (only server-side graph-image rendering paths).
- **How to call the endpoints** — `curl` examples for `problems.php` and `scores.php`
  with sample request/response.

## 8. Testing

- `QuestionService` is unit-testable without HTTP: construct a request DTO, assert on
  the result DTO. Runs under the existing Codeception unit suite inside the php-fpm
  container.
- Phase 0 acceptance: both endpoints return a valid success envelope for a known
  sample question (render produces `question` HTML + `answers`; score returns
  `scores` for a correct answer = 1).

## 9. Out of Scope (later phases)

- Phase 1: strip LMS server code (`course/`, `forums/`, `lti/`, `admin/`, root LMS files).
- Phase 2: strip frontend assets (`javascript/`, `katex/`, `mathquill/`, `tinymce8/`, etc.).
- Phase 3: strip DB/install infra (`migrations/`, `setupdb.php`, `install.php`, etc.).
- Phase 4: prune `includes/`/`assess2/` to engine-referenced files; update docs.
- Dropped features: `convert_to_latex`, `showplot_with_functions`/`function_list`,
  prettified solution, separate drawing endpoint.
