<?php

// Run in the browser by PHP WebAssembly, see public/js/php.js.

require __DIR__ . '/../../vendor/autoload.php';

use EmojiConverter\EmojiConverter;

/** @var array{direction: 'from-emoji'|'to-emoji', catalog: string, text: string} $request */
$request = json_decode(file_get_contents('/tmp/request.json'), true, flags: \JSON_THROW_ON_ERROR);

$converter = new EmojiConverter();

$text = match ($request['direction']) {
    'from-emoji' => $converter->fromEmoji($request['catalog'], $request['text']),
    'to-emoji' => $converter->toEmoji($request['catalog'], $request['text']),
};

file_put_contents('/tmp/response.txt', $text);
