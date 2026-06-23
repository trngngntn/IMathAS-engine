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
