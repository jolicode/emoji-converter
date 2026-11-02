<?php

namespace EmojiConverter;

final readonly class UnknownShortcode
{
    /**
     * @param list<string> $suggestions Known shortcodes that look like it, the closest first
     */
    public function __construct(
        public string $shortcode,
        public array $suggestions,
    ) {
    }
}
