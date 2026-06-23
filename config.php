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
