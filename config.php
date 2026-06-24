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

// The engine localizes strings via gettext's _(). When the gettext extension
// is loaded (default in the Docker image), _() is the built-in and untranslated
// strings pass through unchanged. This fallback keeps the engine working if the
// extension is absent. No translation catalogs are shipped (API-only engine).
if (!function_exists('_')) {
    function _($s) { return $s; }
}
