<?php

declare(strict_types=1);

namespace IMathAS\Engine;

/**
 * Captures PHP warnings/notices/deprecations raised while handling a request
 * (largely from the legacy engine eval'ing question code) instead of silently
 * suppressing them. The collected entries are surfaced in the response's
 * `diagnostics` field so callers get full visibility per request.
 *
 * `display_errors` is forced off so nothing ever leaks into the JSON output
 * stream; the handler is the only path these messages take.
 */
final class Diagnostics
{
    /** @var array<string, array{level: string, message: string, file: string, line: int, count: int}> */
    private static array $entries = [];
    private static bool $installed = false;

    public static function install(): void
    {
        if (self::$installed) {
            return;
        }
        self::$installed = true;

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        set_error_handler(self::handle(...));
    }

    /**
     * Error handler: record the diagnostic and suppress default output/logging.
     * Returns false for @-suppressed errors so PHP's normal (silent) handling
     * applies, matching the engine's intentional `@` usage.
     */
    public static function handle(int $errno, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $errno) === 0) {
            return false;
        }

        $level = self::levelName($errno);
        $key = $level . '|' . $message . '|' . $file . '|' . $line;

        if (isset(self::$entries[$key])) {
            self::$entries[$key]['count']++;
        } else {
            self::$entries[$key] = [
                'level' => $level,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'count' => 1,
            ];
        }

        return true;
    }

    /**
     * Return the diagnostics collected so far and reset the buffer.
     *
     * @return list<array{level: string, message: string, file: string, line: int, count: int}>
     */
    public static function drain(): array
    {
        $out = array_values(self::$entries);
        self::$entries = [];
        return $out;
    }

    private static function levelName(int $errno): string
    {
        return match ($errno) {
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'Warning',
            E_NOTICE, E_USER_NOTICE => 'Notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
            E_USER_ERROR => 'Error',
            default => 'Other',
        };
    }
}
