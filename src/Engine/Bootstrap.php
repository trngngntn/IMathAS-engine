<?php

declare(strict_types=1);

namespace IMathAS\Engine;

use PDO;

/**
 * DB-less runtime initialization for the standalone question engine.
 * Replaces the init.php -> validate.php -> config.php chain with only what
 * the assessment engine reads at runtime. No MySQL, no auth, no LMS. This is
 * also the engine's sole config surface — there is no separate config file.
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

        // (The _() gettext shim is defined globally by src/Engine/functions.php,
        // loaded first by src/Engine/autoload.php, so it is always available.)

        // Web path roots used by the engine when building asset URLs in the
        // generated HTML. Empty = served at the web root (relative URLs).
        $GLOBALS['imasroot'] = '';
        $GLOBALS['staticroot'] = '';
        // LMS site globals the engine occasionally references when building
        // links (e.g. [EMBED]); empty/no-course in this standalone context.
        $GLOBALS['basesiteurl'] = '';
        $GLOBALS['cid'] = '';

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

        // The API caller is the system, treated as a site admin (100). This
        // gives author-level answer handling (`> 10`) and full error visibility
        // (`=== 100` → $showallerrors). The internal context the engine assumes
        // under $showallerrors is provided below / in QuestionService so it does
        // not emit spurious "undefined key" warnings into `errors`.
        $GLOBALS['myrights'] = 100;

        // The engine reads $useeqnhelper to expose the equation helper in jsparams.
        $GLOBALS['useeqnhelper'] = 1;

        // Render preferences normally set by a logged-in session. mathdisp != 2
        // keeps math client-side (2 = server image fallback).
        $_SESSION['userprefs']['graphdisp'] = 1;
        $_SESSION['userprefs']['drawentry'] = 1;
        $_SESSION['userprefs']['mathdisp'] = 0;
        $_SESSION['graphdisp'] = 1;
        $_SESSION['mathdisp'] = 0;
        $GLOBALS['hide-sronly'] = true;

        require_once $root . '/includes/sanitize.php';

        self::$initialized = true;
    }
}
