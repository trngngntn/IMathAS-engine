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
