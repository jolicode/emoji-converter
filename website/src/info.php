<?php

// Run in the browser by PHP WebAssembly, see public/js/php.js.

file_put_contents('/tmp/response.txt', json_encode([
    'icu' => \INTL_ICU_VERSION,
    'php' => \PHP_VERSION,
], \JSON_THROW_ON_ERROR));
