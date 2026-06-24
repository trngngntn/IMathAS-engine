<?php

// Global gettext shim. The engine localizes user-facing strings via _(), but
// ships no translation catalogs and no longer requires the gettext extension.
// When gettext IS loaded, _() is its built-in and this definition is skipped.
// Loaded first by src/Engine/autoload.php so _() exists before any engine code,
// independent of the extension or Bootstrap ordering.
//
// NOTE: intentionally in the global namespace (no `namespace` declaration) so
// it defines the global _() the engine calls.

if (!function_exists('_')) {
    function _($s)
    {
        return $s;
    }
}
