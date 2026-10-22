<?php

// Run in the browser by PHP WebAssembly, see public/js/php.js: the request is
// on stdin, the result on stdout, the errors on stderr.

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/../../vendor/autoload.php';

use EmojiConverter\EmojiConverter;

/** @var array{direction: 'from-emoji'|'to-emoji', catalog: string, text: string} $request */
$request = json_decode(file_get_contents('php://stdin'), true, flags: \JSON_THROW_ON_ERROR);

$converter = new EmojiConverter();

$text = match ($request['direction']) {
    'from-emoji' => $converter->fromEmoji($request['catalog'], $request['text']),
    'to-emoji' => $converter->toEmoji($request['catalog'], $request['text']),
};

echo $text;
