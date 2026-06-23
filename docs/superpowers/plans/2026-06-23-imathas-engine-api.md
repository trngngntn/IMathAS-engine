# IMathAS Question Engine API — Implementation Plan (Phase 0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a DB-less, API-only PHP service that wraps the IMathAS assessment engine, exposing `problems.php` (render) and `scores.php` (grade) backed by a clean, unit-testable `IMathAS\Engine` service layer.

**Architecture:** Thin endpoint files parse the HTTP request, delegate to a single `QuestionService` that wraps the existing `AssessStandalone` engine, and emit a uniform `{ok, data, errors}` JSON envelope. A DB-less `Bootstrap` sets up the globals/constants the legacy engine reads (no MySQL, no `validate.php`, no LMS). The engine gets a throwaway in-memory SQLite `PDO` handle that is never queried in the inject-data flow.

**Tech Stack:** PHP 8.4, Composer (PSR-4 autoload), PHPUnit 11, Docker (`php:8.4-fpm` + nginx), the existing IMathAS engine (`assess2/`, `assessment/`, `filter/`).

## Global Constraints

- PHP version: **8.4** (officially supported by IMathAS).
- **No database**: no MySQL service, no PDO MySQL connection, no DB session handler. The only `PDO` is a throwaway `new PDO('sqlite::memory:')`.
- **Pure engine output only**: no `convert_to_latex`, no `showplot_with_functions`/`function_list`, no prettified-solution pass.
- New code lives under `src/Engine/`, namespace `IMathAS\Engine\`, PSR-4.
- All new code uses 8.4 idioms: `readonly` promoted constructors, enums, typed properties/returns, `match`.
- Engine question-slot index is the constant `QuestionService::QUESTION_SLOT` (value `27`, arbitrary but consistent across question data + state).
- The author-supplied `control` contains the full setup including the `$answer` assignment. The student answer is delivered to the engine via `$_POST['qn'.QUESTION_SLOT]`.
- Response envelope: success → HTTP 200 `{ "ok": true, "data": {...}, "errors": [...] }`; bad input → HTTP 400 `{ "ok": false, "error": {"code","message"} }`; wrong method → HTTP 405 same failure shape.
- PHP has no host binary; **all PHP/Composer/PHPUnit commands run inside the `php` container** via `docker compose exec php ...`.
- nginx is published on host port **8088**.

---

### Task 1: Composer autoload + PHPUnit tooling

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `src/Engine/Version.php` (trivial class to prove autoload works)
- Test: `tests/Engine/VersionTest.php`

**Interfaces:**
- Produces: PSR-4 autoload mapping `IMathAS\Engine\ → src/Engine/`; PHPUnit runnable via `vendor/bin/phpunit`.

- [ ] **Step 1: Rewrite `composer.json`**

The existing `require-dev` pins Codeception `^2.4`, which cannot install on PHP 8.4. Replace it with PHPUnit 11 and add PSR-4 autoload. Full new contents:

```json
{
    "require": {
        "php": ">=8.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^11"
    },
    "autoload": {
        "psr-4": {
            "IMathAS\\Engine\\": "src/Engine/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "IMathAS\\Engine\\Tests\\": "tests/Engine/"
        }
    },
    "config": {
        "process-timeout": 0
    },
    "scripts": {
        "test": "phpunit"
    }
}
```

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnWarning="true"
         failOnDeprecation="false">
    <testsuites>
        <testsuite name="engine">
            <directory>tests/Engine</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create `src/Engine/Version.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine;

final class Version
{
    public const string ENGINE_API = '0.1.0';
}
```

- [ ] **Step 4: Write the failing test `tests/Engine/VersionTest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function test_engine_api_version_is_exposed(): void
    {
        self::assertSame('0.1.0', Version::ENGINE_API);
    }
}
```

- [ ] **Step 5: Install deps and run the test (inside container — see Task 2 if the container is not up yet)**

Run:
```bash
docker compose exec php composer update --no-interaction
docker compose exec php vendor/bin/phpunit --testdox
```
Expected: `VersionTest` passes. (If the container does not exist yet, do Task 2 first, then return and run this step.)

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist src/Engine/Version.php tests/Engine/VersionTest.php
git commit -m "build: add PSR-4 autoload + PHPUnit 11 tooling for engine API"
```

---

### Task 2: Docker baseline (no MySQL)

**Files:**
- Create: `docker-compose.yml`
- Create: `Dockerfile`
- Create: `default.conf`
- Create: `probe.php` (temporary health probe, deleted at end of task)

**Interfaces:**
- Produces: running `php` (php:8.4-fpm) and `web` (nginx) services; `*.php` served at `http://localhost:8088/`.

- [ ] **Step 1: Create `Dockerfile`**

Installs the engine's required extensions (`mbstring`, `gettext`) on top of the official 8.4 image. `pdo_sqlite` is bundled by default. Composer is copied from the official composer image.

```dockerfile
FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libgettextpo-dev gettext \
    && docker-php-ext-install mbstring gettext \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

- [ ] **Step 2: Create `default.conf`**

```
server {
    listen 80;
    index index.php;
    root /var/www/html;
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }
}
```

- [ ] **Step 3: Create `docker-compose.yml`**

```yaml
services:
  web:
    image: nginx:latest
    ports:
      - "8088:80"
    volumes:
      - ./:/var/www/html
      - ./default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
  php:
    build:
      context: ./
    volumes:
      - ./:/var/www/html
```

- [ ] **Step 4: Create `probe.php`**

```php
<?php
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'php' => PHP_VERSION]);
```

- [ ] **Step 5: Build and start, then probe**

Run:
```bash
docker compose up -d --build
curl -s http://localhost:8088/probe.php
```
Expected: `{"ok":true,"php":"8.4.x"}`

- [ ] **Step 6: Verify required extensions are present**

Run:
```bash
docker compose exec php php -m | grep -E "mbstring|pdo_sqlite|gettext"
```
Expected: all three listed.

- [ ] **Step 7: Remove the probe and commit**

```bash
rm probe.php
git add docker-compose.yml Dockerfile default.conf
git commit -m "build: DB-less Docker baseline (php 8.4-fpm + nginx)"
```

---

### Task 3: DB-less config + Bootstrap

**Files:**
- Create: `config.php`
- Create: `src/Engine/Bootstrap.php`
- Test: `tests/Engine/BootstrapTest.php`

**Interfaces:**
- Consumes: `config.php` defines `$CFG`, `$installname`, `$imasroot`, `$staticroot`, error_reporting.
- Produces: `Bootstrap::init(): void` — idempotent; after it runs, `$GLOBALS['DBH']` is a `PDO`, `$GLOBALS['myrights'] === 0`, constants `MYSQL_LEFT_WRDBND`/`MYSQL_RIGHT_WRDBND` defined, `$_SESSION['userprefs']` render prefs set, `includes/sanitize.php` + `i18n/i18n.php` loaded.

- [ ] **Step 1: Create `config.php`**

```php
<?php
// DB-less engine config. No MySQL credentials, no PDO connection here.

// Keep legacy-engine notice/deprecation/warning noise out of JSON responses.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$installname = 'IMathAS-Engine';
$imasroot = '';
$staticroot = '';

$CFG = [
    'GEN' => [
        'newpasswords' => 'only',
    ],
];
```

- [ ] **Step 2: Write the failing test `tests/Engine/BootstrapTest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Bootstrap;
use PDO;
use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    public function test_init_sets_up_dbless_engine_globals(): void
    {
        Bootstrap::init();

        self::assertInstanceOf(PDO::class, $GLOBALS['DBH']);
        self::assertSame(0, $GLOBALS['myrights']);
        self::assertTrue(defined('MYSQL_LEFT_WRDBND'));
        self::assertTrue(defined('MYSQL_RIGHT_WRDBND'));
        self::assertSame(1, $_SESSION['userprefs']['graphdisp']);
        self::assertSame(1, $_SESSION['userprefs']['drawentry']);
    }

    public function test_init_is_idempotent(): void
    {
        Bootstrap::init();
        $first = $GLOBALS['DBH'];
        Bootstrap::init();
        self::assertSame($first, $GLOBALS['DBH']);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `docker compose exec php vendor/bin/phpunit --filter BootstrapTest`
Expected: FAIL — class `IMathAS\Engine\Bootstrap` not found.

- [ ] **Step 4: Create `src/Engine/Bootstrap.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine;

use PDO;

/**
 * DB-less runtime initialization for the standalone question engine.
 * Replaces the init.php -> validate.php -> config.php chain with only what
 * the assessment engine reads at runtime. No MySQL, no auth, no LMS.
 */
final class Bootstrap
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $root = dirname(__DIR__, 2);

        require_once $root . '/config.php';

        // Constants the engine references (MySQL 8+ word-boundary variants).
        if (!defined('MYSQL_LEFT_WRDBND')) {
            define('MYSQL_LEFT_WRDBND', '\\b');
        }
        if (!defined('MYSQL_RIGHT_WRDBND')) {
            define('MYSQL_RIGHT_WRDBND', '\\b');
        }
        if (!defined('JSON_INVALID_UTF8_IGNORE')) {
            define('JSON_INVALID_UTF8_IGNORE', 0);
        }

        // Throwaway DB handle: satisfies the engine's non-nullable PDO type
        // hints. Never queried in the inject-data flow; fails loudly if it is.
        if (!isset($GLOBALS['DBH']) || !($GLOBALS['DBH'] instanceof PDO)) {
            $GLOBALS['DBH'] = new PDO('sqlite::memory:');
        }

        // Guest rights; no authenticated user.
        $GLOBALS['myrights'] = 0;

        // The engine reads $useeqnhelper to expose the equation helper in jsparams.
        $GLOBALS['useeqnhelper'] = 1;

        // Render preferences normally set by a logged-in session.
        $_SESSION['userprefs']['graphdisp'] = 1;
        $_SESSION['userprefs']['drawentry'] = 1;
        $_SESSION['graphdisp'] = 1;
        $GLOBALS['hide-sronly'] = true;

        require_once $root . '/includes/sanitize.php';
        require_once $root . '/i18n/i18n.php';

        self::$initialized = true;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter BootstrapTest`
Expected: PASS (both tests).

If a fatal occurs about a missing global/constant the engine needs, add it to `Bootstrap::init()` (the set above is derived from `init.php`/`AssessStandalone`; additions go in the same place). Do not add a DB connection.

- [ ] **Step 6: Commit**

```bash
git add config.php src/Engine/Bootstrap.php tests/Engine/BootstrapTest.php
git commit -m "feat: DB-less Bootstrap + engine config"
```

---

### Task 4: Exception, Stype enum, and request/result DTOs

**Files:**
- Create: `src/Engine/EngineException.php`
- Create: `src/Engine/Dto/Stype.php`
- Create: `src/Engine/Dto/RenderRequest.php`
- Create: `src/Engine/Dto/RenderResult.php`
- Create: `src/Engine/Dto/ScoreRequest.php`
- Create: `src/Engine/Dto/ScoreResult.php`
- Test: `tests/Engine/Dto/RenderRequestTest.php`
- Test: `tests/Engine/Dto/ScoreRequestTest.php`

**Interfaces:**
- Produces:
  - `EngineException extends \RuntimeException` with `public readonly string $errorCode`.
  - `Stype` enum: cases `Template` (`'template'`), `Code` (`'code'`); `Stype::fromString(?string): self` defaulting to `Template`.
  - `RenderRequest::fromArray(array $data): self` with `readonly` props `string $qtype, string $control, string $qtext, string $solution, int $seed, Stype $stype`.
  - `RenderResult` readonly props `int $seed, string $question, string $solution, array $vars, array $answers, array $jsparams`; `toArray(): array`.
  - `ScoreRequest::fromArray(array $data): self` with `readonly` props `string $qtype, string $control, int $seed, string $answer, ?array $partsToScore`.
  - `ScoreResult` readonly props `array $scores, array $raw, array $answeights, bool $allAnswered`; `toArray(): array`.

- [ ] **Step 1: Create `src/Engine/EngineException.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine;

use RuntimeException;

final class EngineException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
```

- [ ] **Step 2: Create `src/Engine/Dto/Stype.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

enum Stype: string
{
    case Template = 'template';
    case Code = 'code';

    public static function fromString(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Template;
        }
        return self::from($value);
    }
}
```

- [ ] **Step 3: Write failing test `tests/Engine/Dto/RenderRequestTest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Dto;

use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\Dto\Stype;
use IMathAS\Engine\EngineException;
use PHPUnit\Framework\TestCase;

final class RenderRequestTest extends TestCase
{
    public function test_builds_from_full_payload(): void
    {
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => '$a = 5',
            'qtext' => 'Find $a',
            'solution' => 'because',
            'seed' => 42,
            'stype' => 'code',
        ]);

        self::assertSame('number', $req->qtype);
        self::assertSame('$a = 5', $req->control);
        self::assertSame('Find $a', $req->qtext);
        self::assertSame('because', $req->solution);
        self::assertSame(42, $req->seed);
        self::assertSame(Stype::Code, $req->stype);
    }

    public function test_defaults_solution_stype_and_random_seed(): void
    {
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => '$a = 5',
            'qtext' => 'Find $a',
        ]);

        self::assertSame('', $req->solution);
        self::assertSame(Stype::Template, $req->stype);
        self::assertGreaterThanOrEqual(0, $req->seed);
    }

    public function test_missing_required_field_throws_invalid_request(): void
    {
        $this->expectException(EngineException::class);
        RenderRequest::fromArray(['qtype' => 'number', 'control' => '$a=5']); // no qtext
    }
}
```

- [ ] **Step 4: Run to verify it fails**

Run: `docker compose exec php vendor/bin/phpunit --filter RenderRequestTest`
Expected: FAIL — `RenderRequest` not found.

- [ ] **Step 5: Create `src/Engine/Dto/RenderRequest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

use IMathAS\Engine\EngineException;

final class RenderRequest
{
    public function __construct(
        public readonly string $qtype,
        public readonly string $control,
        public readonly string $qtext,
        public readonly string $solution,
        public readonly int $seed,
        public readonly Stype $stype,
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['qtype', 'control', 'qtext'] as $required) {
            if (!isset($data[$required]) || !is_string($data[$required]) || $data[$required] === '') {
                throw new EngineException('invalid_request', "Missing or empty required field: {$required}");
            }
        }

        $seed = isset($data['seed']) ? (int) $data['seed'] : random_int(0, 10000);

        return new self(
            qtype: $data['qtype'],
            control: $data['control'],
            qtext: $data['qtext'],
            solution: isset($data['solution']) && is_string($data['solution']) ? $data['solution'] : '',
            seed: $seed,
            stype: Stype::fromString($data['stype'] ?? null),
        );
    }
}
```

- [ ] **Step 6: Run to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter RenderRequestTest`
Expected: PASS.

- [ ] **Step 7: Create `src/Engine/Dto/RenderResult.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

final class RenderResult
{
    public function __construct(
        public readonly int $seed,
        public readonly string $question,
        public readonly string $solution,
        public readonly array $vars,
        public readonly array $answers,
        public readonly array $jsparams,
    ) {
    }

    public function toArray(): array
    {
        return [
            'seed' => $this->seed,
            'question' => $this->question,
            'solution' => $this->solution,
            'vars' => $this->vars,
            'answers' => $this->answers,
            'jsparams' => $this->jsparams,
        ];
    }
}
```

- [ ] **Step 8: Write failing test `tests/Engine/Dto/ScoreRequestTest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Dto;

use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\EngineException;
use PHPUnit\Framework\TestCase;

final class ScoreRequestTest extends TestCase
{
    public function test_builds_from_full_payload(): void
    {
        $req = ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => '$answer = 12',
            'seed' => 7,
            'answer' => '12',
            'partsToScore' => [0],
        ]);

        self::assertSame('number', $req->qtype);
        self::assertSame('$answer = 12', $req->control);
        self::assertSame(7, $req->seed);
        self::assertSame('12', $req->answer);
        self::assertSame([0], $req->partsToScore);
    }

    public function test_parts_to_score_defaults_to_null(): void
    {
        $req = ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => '$answer = 12',
            'seed' => 7,
            'answer' => '12',
        ]);

        self::assertNull($req->partsToScore);
    }

    public function test_missing_answer_throws_invalid_request(): void
    {
        $this->expectException(EngineException::class);
        ScoreRequest::fromArray(['qtype' => 'number', 'control' => '$answer=12', 'seed' => 7]);
    }
}
```

- [ ] **Step 9: Run to verify it fails**

Run: `docker compose exec php vendor/bin/phpunit --filter ScoreRequestTest`
Expected: FAIL — `ScoreRequest` not found.

- [ ] **Step 10: Create `src/Engine/Dto/ScoreRequest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

use IMathAS\Engine\EngineException;

final class ScoreRequest
{
    public function __construct(
        public readonly string $qtype,
        public readonly string $control,
        public readonly int $seed,
        public readonly string $answer,
        public readonly ?array $partsToScore,
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['qtype', 'control', 'answer'] as $required) {
            if (!isset($data[$required]) || !is_string($data[$required]) || $data[$required] === '') {
                throw new EngineException('invalid_request', "Missing or empty required field: {$required}");
            }
        }
        if (!isset($data['seed']) || !is_numeric($data['seed'])) {
            throw new EngineException('invalid_request', 'Missing or non-numeric required field: seed');
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
            answer: $data['answer'],
            partsToScore: $parts,
        );
    }
}
```

- [ ] **Step 11: Run to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter ScoreRequestTest`
Expected: PASS.

- [ ] **Step 12: Create `src/Engine/Dto/ScoreResult.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

final class ScoreResult
{
    public function __construct(
        public readonly array $scores,
        public readonly array $raw,
        public readonly array $answeights,
        public readonly bool $allAnswered,
    ) {
    }

    public function toArray(): array
    {
        return [
            'scores' => $this->scores,
            'raw' => $this->raw,
            'answeights' => $this->answeights,
            'allAnswered' => $this->allAnswered,
        ];
    }
}
```

- [ ] **Step 13: Commit**

```bash
git add src/Engine/EngineException.php src/Engine/Dto tests/Engine/Dto
git commit -m "feat: engine request/result DTOs with validation"
```

---

### Task 5: HTTP layer (request parsing + JSON response)

**Files:**
- Create: `src/Engine/Http/JsonRequest.php`
- Create: `src/Engine/Http/JsonResponse.php`
- Test: `tests/Engine/Http/JsonRequestTest.php`

**Interfaces:**
- Consumes: `EngineException`.
- Produces:
  - `JsonRequest::requirePost(string $method): void` — throws `EngineException('method_not_allowed', ...)` if `$method !== 'POST'`.
  - `JsonRequest::parseJsonBody(string $raw): array` — decodes JSON object body; throws `EngineException('invalid_request', ...)` on non-array.
  - `JsonResponse::success(array $data, array $errors = []): void` and `JsonResponse::failure(string $code, string $message, int $status): void` — set status + `Content-Type` and echo the envelope. Both take an optional injected "emitter" callable for testability (default: real `header()`/`echo`).

- [ ] **Step 1: Write failing test `tests/Engine/Http/JsonRequestTest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests\Http;

use IMathAS\Engine\EngineException;
use IMathAS\Engine\Http\JsonRequest;
use PHPUnit\Framework\TestCase;

final class JsonRequestTest extends TestCase
{
    public function test_require_post_rejects_get(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Method Not Allowed');
        JsonRequest::requirePost('GET');
    }

    public function test_require_post_allows_post(): void
    {
        JsonRequest::requirePost('POST');
        $this->expectNotToPerformAssertions();
    }

    public function test_parse_json_body_returns_array(): void
    {
        self::assertSame(['a' => 1], JsonRequest::parseJsonBody('{"a":1}'));
    }

    public function test_parse_json_body_rejects_non_object(): void
    {
        $this->expectException(EngineException::class);
        JsonRequest::parseJsonBody('not json');
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php vendor/bin/phpunit --filter JsonRequestTest`
Expected: FAIL — `JsonRequest` not found.

- [ ] **Step 3: Create `src/Engine/Http/JsonRequest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Http;

use IMathAS\Engine\EngineException;

final class JsonRequest
{
    public static function requirePost(string $method): void
    {
        if (strtoupper($method) !== 'POST') {
            throw new EngineException('method_not_allowed', 'Method Not Allowed');
        }
    }

    public static function parseJsonBody(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new EngineException('invalid_request', 'Request body is not a valid JSON object');
        }
        return $data;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter JsonRequestTest`
Expected: PASS.

- [ ] **Step 5: Create `src/Engine/Http/JsonResponse.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Http;

final class JsonResponse
{
    /** @var callable(int,string):void */
    private $emit;

    /**
     * @param (callable(int,string):void)|null $emit Status+body emitter; defaults to real HTTP output.
     */
    public function __construct(?callable $emit = null)
    {
        $this->emit = $emit ?? static function (int $status, string $body): void {
            http_response_code($status);
            header('Content-Type: application/json');
            echo $body;
        };
    }

    public function success(array $data, array $errors = []): void
    {
        ($this->emit)(200, (string) json_encode(
            ['ok' => true, 'data' => $data, 'errors' => array_values($errors)],
            JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));
    }

    public function failure(string $code, string $message, int $status): void
    {
        ($this->emit)($status, (string) json_encode(
            ['ok' => false, 'error' => ['code' => $code, 'message' => $message]],
            JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Engine/Http tests/Engine/Http
git commit -m "feat: HTTP request parsing + JSON response envelope"
```

---

### Task 6: QuestionService::render + problems.php endpoint

**Files:**
- Create: `src/Engine/QuestionService.php` (render now; score added in Task 7)
- Create: `problems.php`
- Test: `tests/Engine/QuestionServiceRenderTest.php`

**Interfaces:**
- Consumes: `Bootstrap`, `RenderRequest`, `RenderResult`, `AssessStandalone` (legacy engine).
- Produces:
  - `QuestionService::QUESTION_SLOT` (int constant `27`).
  - `new QuestionService(\PDO $dbh)`.
  - `QuestionService::render(RenderRequest $req): RenderResult`.
  - `QuestionService::defaultQuestionData(): array` (private; the `imas_questionset`-shaped base record).

- [ ] **Step 1: Write failing test `tests/Engine/QuestionServiceRenderTest.php`**

Uses a deterministic single-part `number` question (`$a=5; $b=7; $answer=$a+$b`). The correct answer (12) appears in `answers`; the substituted prompt appears in `question`.

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\QuestionService;
use PHPUnit\Framework\TestCase;

final class QuestionServiceRenderTest extends TestCase
{
    private function service(): QuestionService
    {
        Bootstrap::init();
        require_once dirname(__DIR__, 2) . '/assess2/AssessStandalone.php';
        return new QuestionService($GLOBALS['DBH']);
    }

    public function test_renders_a_simple_number_question(): void
    {
        $req = RenderRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$b = 7\n\$answer = \$a + \$b",
            'qtext' => 'Find $a + $b',
            'seed' => 1234,
        ]);

        $result = $this->service()->render($req);

        self::assertSame(1234, $result->seed);
        self::assertStringContainsString('Find 5 + 7', $result->question);
        self::assertNotEmpty($result->answers);
        // The correct answer (12) is surfaced for the single part.
        self::assertStringContainsString('12', implode(' ', array_map('strval', (array) $result->answers)));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php vendor/bin/phpunit --filter QuestionServiceRenderTest`
Expected: FAIL — `QuestionService` not found.

- [ ] **Step 3: Create `src/Engine/QuestionService.php` (render)**

```php
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
        extract($sanitized);
        ob_start();
        eval($code);
        return (string) ob_get_clean();
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
        ];
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter QuestionServiceRenderTest`
Expected: PASS. If a fatal traces into the engine requiring a missing global, add it to `Bootstrap::init()` (Task 3, Step 5 note). Confirm no SQLite query error appears — that would mean a DB path is hit and must be investigated before proceeding.

- [ ] **Step 5: Create `problems.php`**

```php
<?php

declare(strict_types=1);

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\RenderRequest;
use IMathAS\Engine\EngineException;
use IMathAS\Engine\Http\JsonRequest;
use IMathAS\Engine\Http\JsonResponse;
use IMathAS\Engine\QuestionService;

require_once __DIR__ . '/vendor/autoload.php';

Bootstrap::init();
require_once __DIR__ . '/assess2/AssessStandalone.php';

$response = new JsonResponse();

try {
    JsonRequest::requirePost($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $payload = JsonRequest::parseJsonBody(file_get_contents('php://input') ?: '');
    $result = (new QuestionService($GLOBALS['DBH']))->render(RenderRequest::fromArray($payload));
    $response->success($result->toArray());
} catch (EngineException $e) {
    $status = $e->errorCode === 'method_not_allowed' ? 405 : 400;
    $response->failure($e->errorCode, $e->getMessage(), $status);
}
```

- [ ] **Step 6: Verify the live endpoint**

Run:
```bash
curl -s -X POST http://localhost:8088/problems.php \
  -H 'Content-Type: application/json' \
  -d '{"qtype":"number","control":"$a = 5\n$b = 7\n$answer = $a + $b","qtext":"Find $a + $b","seed":1234}' \
  | head -c 600
```
Expected: JSON `{"ok":true,"data":{"seed":1234,"question":"...Find 5 + 7...","answers":...}, "errors":[]}`.

Also verify method guard:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8088/problems.php
```
Expected: `405`.

- [ ] **Step 7: Commit**

```bash
git add src/Engine/QuestionService.php problems.php tests/Engine/QuestionServiceRenderTest.php
git commit -m "feat: QuestionService.render + problems.php endpoint"
```

---

### Task 7: QuestionService::score + scores.php endpoint

**Files:**
- Modify: `src/Engine/QuestionService.php` (add `score()`)
- Create: `scores.php`
- Test: `tests/Engine/QuestionServiceScoreTest.php`

**Interfaces:**
- Consumes: `ScoreRequest`, `ScoreResult`.
- Produces: `QuestionService::score(ScoreRequest $req): ScoreResult`.

- [ ] **Step 1: Write failing test `tests/Engine/QuestionServiceScoreTest.php`**

```php
<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\QuestionService;
use PHPUnit\Framework\TestCase;

final class QuestionServiceScoreTest extends TestCase
{
    private function service(): QuestionService
    {
        Bootstrap::init();
        require_once dirname(__DIR__, 2) . '/assess2/AssessStandalone.php';
        return new QuestionService($GLOBALS['DBH']);
    }

    public function test_correct_answer_scores_full(): void
    {
        $result = $this->service()->score(ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$b = 7\n\$answer = \$a + \$b",
            'seed' => 1234,
            'answer' => '12',
        ]));

        self::assertEqualsWithDelta(1.0, $result->scores[0] ?? 0, 0.01);
        self::assertTrue($result->allAnswered);
    }

    public function test_wrong_answer_scores_zero(): void
    {
        $result = $this->service()->score(ScoreRequest::fromArray([
            'qtype' => 'number',
            'control' => "\$a = 5\n\$b = 7\n\$answer = \$a + \$b",
            'seed' => 1234,
            'answer' => '99',
        ]));

        self::assertEqualsWithDelta(0.0, $result->scores[0] ?? 1, 0.01);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php vendor/bin/phpunit --filter QuestionServiceScoreTest`
Expected: FAIL — `score()` not defined (Error).

- [ ] **Step 3: Add `score()` and import to `src/Engine/QuestionService.php`**

Add these imports near the top (with the existing `use` lines):

```php
use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\Dto\ScoreResult;
```

Add this method to the class (after `render()`):

```php
    public function score(ScoreRequest $req): ScoreResult
    {
        $a2 = new AssessStandalone($this->dbh);

        $qdata = $this->defaultQuestionData();
        $qdata['qtype'] = $req->qtype;
        $qdata['control'] = $req->control;

        $a2->setQuestionData(self::QUESTION_SLOT, $qdata);
        $a2->setState($this->freshState($req->seed));

        // The engine reads the student answer from $_POST['qn'.<slot>].
        $_POST['qn' . self::QUESTION_SLOT] = $req->answer;

        $partsToScore = true;
        if ($req->partsToScore !== null) {
            $partsToScore = [];
            foreach ($req->partsToScore as $pn) {
                $partsToScore[$pn] = true;
            }
        }

        $result = $a2->scoreQuestion(self::QUESTION_SLOT, $partsToScore);

        return new ScoreResult(
            scores: array_values($result['scores'] ?? []),
            raw: array_values($result['raw'] ?? []),
            answeights: array_values($result['answeights'] ?? []),
            allAnswered: (bool) ($result['allans'] ?? false),
        );
    }
```

- [ ] **Step 4: Run to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter QuestionServiceScoreTest`
Expected: PASS (both correct→1.0 and wrong→0.0).

- [ ] **Step 5: Create `scores.php`**

Accepts form-encoded body (matching the engine's `$_POST` convention). `partsToScore` may be a JSON string.

```php
<?php

declare(strict_types=1);

use IMathAS\Engine\Bootstrap;
use IMathAS\Engine\Dto\ScoreRequest;
use IMathAS\Engine\EngineException;
use IMathAS\Engine\Http\JsonRequest;
use IMathAS\Engine\Http\JsonResponse;
use IMathAS\Engine\QuestionService;

require_once __DIR__ . '/vendor/autoload.php';

Bootstrap::init();
require_once __DIR__ . '/assess2/AssessStandalone.php';

$response = new JsonResponse();

try {
    JsonRequest::requirePost($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $result = (new QuestionService($GLOBALS['DBH']))->score(ScoreRequest::fromArray($_POST));
    $response->success($result->toArray());
} catch (EngineException $e) {
    $status = $e->errorCode === 'method_not_allowed' ? 405 : 400;
    $response->failure($e->errorCode, $e->getMessage(), $status);
}
```

- [ ] **Step 6: Verify the live endpoint**

Run:
```bash
curl -s -X POST http://localhost:8088/scores.php \
  --data-urlencode 'qtype=number' \
  --data-urlencode 'control=$a = 5
$b = 7
$answer = $a + $b' \
  --data-urlencode 'seed=1234' \
  --data-urlencode 'answer=12'
```
Expected: `{"ok":true,"data":{"scores":[1],...,"allAnswered":true},"errors":[]}`.

- [ ] **Step 7: Commit**

```bash
git add src/Engine/QuestionService.php scores.php tests/Engine/QuestionServiceScoreTest.php
git commit -m "feat: QuestionService.score + scores.php endpoint"
```

---

### Task 8: README dependencies/usage + end-to-end smoke script

**Files:**
- Create: `README.engine.md` (engine API readme; keeps legacy `readme.md` untouched until Phase 4)
- Create: `scripts/smoke.sh`

**Interfaces:**
- Consumes: running stack from Task 2; endpoints from Tasks 6–7.

- [ ] **Step 1: Create `scripts/smoke.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
BASE="${1:-http://localhost:8088}"

echo "== render (problems.php) =="
curl -fsS -X POST "$BASE/problems.php" \
  -H 'Content-Type: application/json' \
  -d '{"qtype":"number","control":"$a = 5\n$b = 7\n$answer = $a + $b","qtext":"Find $a + $b","seed":1234}'
echo

echo "== score correct (scores.php) =="
curl -fsS -X POST "$BASE/scores.php" \
  --data-urlencode 'qtype=number' \
  --data-urlencode 'control=$a = 5
$b = 7
$answer = $a + $b' \
  --data-urlencode 'seed=1234' \
  --data-urlencode 'answer=12'
echo

echo "== method guard (expect 405) =="
curl -s -o /dev/null -w '%{http_code}\n' "$BASE/problems.php"
```

- [ ] **Step 2: Make it executable and run it**

Run:
```bash
chmod +x scripts/smoke.sh
./scripts/smoke.sh
```
Expected: render JSON with `"ok":true`, score JSON with `"scores":[1]`, and `405` on the last line.

- [ ] **Step 3: Create `README.engine.md`**

````markdown
# IMathAS Question Engine (API-only)

A DB-less HTTP service that wraps the IMathAS assessment engine to **render**
algorithmic questions and **grade** answers. No LMS, no database, no frontend.

## Requirements

- **Docker** + **Docker Compose** — runs `php:8.4-fpm` + nginx.
- **PHP 8.4** (provided by the container). Extensions:
  - `mbstring` — required (installed in the image).
  - `pdo_sqlite` — required for the throwaway in-memory DB handle (bundled with PHP).
  - `gettext` — recommended for i18n (installed in the image; engine falls back to a `_()` stub if absent).
  - `gd` — optional, only for server-side graph-image rendering paths.
- **Composer** — for PSR-4 autoloading and dev tooling (provided in the container).

## Install & run

```bash
docker compose up -d --build          # build php:8.4-fpm + nginx, start stack
docker compose exec php composer install   # install autoload + dev deps (PHPUnit)
```

The service is then available at `http://localhost:8088`.

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

Response: `{ "ok": true, "data": { "seed", "question", "solution", "vars", "answers", "jsparams" }, "errors": [] }`

### `POST /scores.php` — grade

Request body (form-encoded): `qtype`, `control`, `seed`, `answer` (required),
optional `partsToScore` (JSON array of part indices).

Response: `{ "ok": true, "data": { "scores", "raw", "answeights", "allAnswered" }, "errors": [] }`

Errors: `400` invalid request, `405` wrong method,
`{ "ok": false, "error": { "code", "message" } }`.

## Tests

```bash
docker compose exec php vendor/bin/phpunit --testdox   # unit + integration
./scripts/smoke.sh                                     # live endpoint smoke test
```
````

- [ ] **Step 4: Commit**

```bash
git add README.engine.md scripts/smoke.sh
git commit -m "docs: engine API README + smoke script"
```

---

## Self-Review

**Spec coverage:**
- §3 layout → Tasks 1,3,4,5,6,7 create every listed file. ✓
- §4.1 Bootstrap → Task 3. ✓ §4.2 QuestionService → Tasks 6,7. ✓ §4.3 DTOs/validation → Task 4. ✓ §4.4 HTTP layer → Task 5. ✓
- §5 envelope/contract → JsonResponse (Task 5) + endpoints (Tasks 6,7). ✓
- §6 infra (Docker, no MySQL, error_reporting) → Tasks 2,3. ✓
- §7 dependencies documented in README → Task 8. ✓
- §8 testing (QuestionService unit-testable, sample question acceptance) → Tasks 6,7. ✓
- §2 DB-less via in-memory SQLite handle → Task 3. ✓

**Placeholder scan:** No TBD/TODO; every code/test step shows full content; commands have expected output. ✓

**Type consistency:** `QuestionService::QUESTION_SLOT`, `render(RenderRequest):RenderResult`, `score(ScoreRequest):ScoreResult`, `EngineException->errorCode`, `JsonResponse::success/failure`, `JsonRequest::requirePost/parseJsonBody`, `Stype::Template/Code`, DTO `toArray()` — names/signatures match across tasks. ✓

**Discovery risk acknowledged:** Tasks 3/6 note that if the legacy engine fatals on a missing global at runtime, the fix is to extend `Bootstrap::init()` (never to add a DB). A SQLite query error would signal an unexpected DB path and must be investigated before proceeding.
