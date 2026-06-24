# IMathAS Question Engine (API-only)

A DB-less HTTP service that wraps the IMathAS assessment engine to **render**
algorithmic questions and **grade** answers. No LMS, no database, no frontend.

## Requirements

- **Docker** + **Docker Compose** — runs `php:8.4-fpm` + nginx.
- **PHP 8.4** (provided by the container). Extensions:
  - `mbstring` — required (installed in the image; built against `libonig-dev`).
  - `pdo_sqlite` — required for the throwaway in-memory DB handle (bundled with PHP; not separately installed).
  - `gettext` is **not** required — the engine's `_()` calls use a global shim (`src/Engine/functions.php`).
- **No runtime Composer.** Engine classes load via `src/Engine/autoload.php` (a hand-written `require_once` list). Composer is used only in development to install PHPUnit.

## Run

**Production** — code baked into the image; no Composer, no `vendor/`, no dev deps:

```bash
docker compose up -d --build          # builds the prod image (Dockerfile) + nginx
```

**Development** — source bind-mounted, Composer present for tests:

```bash
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec php composer install   # PHPUnit (dev only)
```

Either way the service is at `http://localhost:8088`. Verify with the smoke script:

```bash
./scripts/smoke.sh                    # asserts render, score, and 405 method guards
```

## Endpoints

### `POST /question` — render

Request body (JSON):

```json
{
  "qtype": "number",
  "control": "$a = 5\n$b = 7\n$answer = $a + $b",
  "qtext": "Find $a + $b",
  "solution": "",
  "seed": 1234,
  "stype": "template"
}
```

- `qtype`, `control`, `qtext` — required. `control` holds the full setup
  **including** the `$answer` assignment.
- `seed` — optional (random if omitted). `solution` — optional. `stype` —
  `template` (default) or `code`.

Response: `{ "ok": true, "data": { "seed", "question", "solution", "vars", "answers", "jsparams" }, "errors": [], "diagnostics": [] }`

### `POST /score` — grade

Request body (form-encoded): `qtype`, `control`, `seed`, `answer` (required),
optional `partsToScore` (JSON array of part indices).

Response: `{ "ok": true, "data": { "scores", "raw", "answeights", "allAnswered" }, "errors": [], "diagnostics": [] }`

Errors: `400` invalid request, `405` wrong method,
`{ "ok": false, "error": { "code", "message" }, "diagnostics": [] }`.

### `errors` vs `diagnostics`

Both are present on every response.

- **`errors`** — engine *domain* errors: problems the engine itself reports
  (bad question code, warnings raised while evaluating it, etc.), as messages.
- **`diagnostics`** — PHP-level warnings/notices/deprecations captured during the
  request *outside* the engine's own error handling (framework/plumbing noise),
  deduplicated, each as `{ "level", "message", "file", "line", "count" }`. Empty
  on a clean request. These were previously suppressed; they are now surfaced so
  callers have full visibility.

## Tests

Tests run in the dev stack (PHPUnit is a dev-only dependency):

```bash
docker compose -f docker-compose.dev.yml exec php vendor/bin/phpunit --testdox
./scripts/smoke.sh                                     # live endpoint smoke test
```
