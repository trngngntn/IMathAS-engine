<?php
// DB-less engine config. No MySQL credentials, no PDO connection here.

// Error reporting is managed by IMathAS\Engine\Diagnostics (installed in
// Bootstrap): it captures PHP warnings/notices/deprecations and returns them in
// each response's `diagnostics` field rather than suppressing them.

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
