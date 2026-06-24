<?php

declare(strict_types=1);

// Hand-written autoloader for the IMathAS\Engine layer. The engine has NO
// runtime Composer dependency — endpoints (and tests) require this file to load
// the service layer, mirroring how the assessment engine itself loads via
// explicit require_once. Composer is used only in dev to install PHPUnit.
//
// When adding a class under src/Engine/, add a require_once line here.

$dir = __DIR__;

require_once $dir . '/functions.php';        // global _() gettext shim
require_once $dir . '/EngineException.php';
require_once $dir . '/Diagnostics.php';
require_once $dir . '/Dto/Stype.php';
require_once $dir . '/Dto/RenderRequest.php';
require_once $dir . '/Dto/RenderResult.php';
require_once $dir . '/Dto/ScoreRequest.php';
require_once $dir . '/Dto/ScoreResult.php';
require_once $dir . '/Http/JsonRequest.php';
require_once $dir . '/Http/JsonResponse.php';
require_once $dir . '/Bootstrap.php';
require_once $dir . '/QuestionService.php';
