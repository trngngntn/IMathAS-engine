<?php

declare(strict_types=1);

// PHPUnit bootstrap. vendor/autoload.php provides the PHPUnit framework and the
// test-class autoloader (composer autoload-dev); src/Engine/autoload.php is the
// engine's own (Composer-free) autoloader — the same one production uses, so
// the test suite exercises it directly.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Engine/autoload.php';
