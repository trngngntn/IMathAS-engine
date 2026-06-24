# IMathAS Question Engine (API-only)

A DB-less HTTP service that wraps the IMathAS assessment engine to **render**
algorithmic questions and **grade** answers. No LMS, no database, no frontend.

## Requirements

- **Docker** + **Docker Compose** — runs `php:8.4-fpm` + nginx.
- **PHP 8.4** (provided by the container). Extensions:
  - `mbstring` — required (installed in the image).
  - `pdo_sqlite` — required for the throwaway in-memory DB handle (bundled with PHP).
  - `gettext` — recommended (installed in the image; provides the built-in `_()`). The engine ships no translation catalogs; `Bootstrap` defines a pass-through `_()` fallback if the extension is absent.
- **unzip** — required OS package (installed in the image; used by Composer to unpack vendor archives).
- **Composer** — for PSR-4 autoloading and dev tooling (provided in the container).

## Install & run

```bash
docker compose up -d --build          # build php:8.4-fpm + nginx, start stack
docker compose exec php composer install   # install autoload + dev deps (PHPUnit)
```

The service is then available at `http://localhost:8088`. Verify the live stack
with the smoke script:

```bash
./scripts/smoke.sh                    # asserts render, score, and 405 method guards
```

## Endpoints

### `POST /problems.php` — render

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

### `POST /scores.php` — grade

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

```bash
docker compose exec php vendor/bin/phpunit --testdox   # unit + integration
./scripts/smoke.sh                                     # live endpoint smoke test
```
