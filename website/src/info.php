<?php

// Run in the browser by PHP WebAssembly, see public/js/php.js: the result is
// on stdout, the errors on stderr.

ini_set('display_errors', '0');
ini_set('log_errors', '1');

echo json_encode([
    'icu' => \INTL_ICU_VERSION,
    'php' => \PHP_VERSION,
], \JSON_THROW_ON_ERROR);
