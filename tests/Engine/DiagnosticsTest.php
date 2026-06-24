<?php

declare(strict_types=1);

namespace IMathAS\Engine\Tests;

use IMathAS\Engine\Diagnostics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the collector via Diagnostics::handle() directly. Triggering real
 * PHP errors would be intercepted by PHPUnit's own error handler (and the
 * suite runs with failOnWarning), so we drive the handler with synthetic args
 * — the same entry point set_error_handler() invokes in production.
 */
final class DiagnosticsTest extends TestCase
{
    private int $prevErrorReporting;

    protected function setUp(): void
    {
        // Mimic the installed state (Diagnostics::install sets E_ALL) so the
        // handler's @-suppression guard passes for synthetic calls. PHPUnit's
        // own error_reporting level would otherwise mask some levels.
        $this->prevErrorReporting = error_reporting(E_ALL);
        Diagnostics::drain(); // start from an empty buffer
    }

    protected function tearDown(): void
    {
        error_reporting($this->prevErrorReporting);
    }

    public function test_collects_an_entry_with_level_message_and_location(): void
    {
        Diagnostics::handle(E_WARNING, 'Undefined array key "x"', '/srv/engine.php', 42);

        $entries = Diagnostics::drain();

        self::assertSame(
            [['level' => 'Warning', 'message' => 'Undefined array key "x"', 'file' => '/srv/engine.php', 'line' => 42, 'count' => 1]],
            $entries,
        );
    }

    public function test_dedupes_identical_diagnostics_with_a_count(): void
    {
        Diagnostics::handle(E_NOTICE, 'Array to string conversion', '/srv/q.php', 1);
        Diagnostics::handle(E_NOTICE, 'Array to string conversion', '/srv/q.php', 1);
        Diagnostics::handle(E_NOTICE, 'Array to string conversion', '/srv/q.php', 1);

        $entries = Diagnostics::drain();

        self::assertCount(1, $entries, 'identical diagnostics should collapse to one entry');
        self::assertSame(3, $entries[0]['count']);
    }

    public function test_distinct_diagnostics_are_separate_entries(): void
    {
        Diagnostics::handle(E_NOTICE, 'msg A', '/a.php', 1);
        Diagnostics::handle(E_NOTICE, 'msg B', '/a.php', 2);

        self::assertCount(2, Diagnostics::drain());
    }

    public function test_drain_clears_the_buffer(): void
    {
        Diagnostics::handle(E_NOTICE, 'one', '/a.php', 1);
        self::assertCount(1, Diagnostics::drain());
        self::assertSame([], Diagnostics::drain());
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function levelCases(): array
    {
        return [
            'warning'    => [E_WARNING, 'Warning'],
            'notice'     => [E_NOTICE, 'Notice'],
            'deprecated' => [E_DEPRECATED, 'Deprecated'],
            'user warn'  => [E_USER_WARNING, 'Warning'],
        ];
    }

    #[DataProvider('levelCases')]
    public function test_classifies_levels(int $errno, string $expected): void
    {
        Diagnostics::handle($errno, 'm', '/a.php', 1);
        self::assertSame($expected, Diagnostics::drain()[0]['level']);
    }

    public function test_respects_at_suppression(): void
    {
        // Under @-suppression PHP lowers error_reporting; handle() should ignore
        // the error (return false) and collect nothing.
        $prev = error_reporting(0);
        try {
            $handled = Diagnostics::handle(E_WARNING, 'suppressed', '/a.php', 1);
        } finally {
            error_reporting($prev);
        }

        self::assertFalse($handled);
        self::assertSame([], Diagnostics::drain());
    }
}
