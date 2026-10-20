<?php

namespace EmojiConverter;

use Symfony\Component\Emoji\EmojiTransliterator;

final class EmojiConverter
{
    /**
     * Replaces emoji with their shortcode (github, gitlab, slack, text), their
     * name in a locale (en, fr, …), or removes them (strip).
     */
    public function fromEmoji(string $catalog, string $text): string
    {
        return EmojiTransliterator::create($catalog)->transliterate($text);
    }

    /**
     * Replaces shortcodes with their emoji. Only the github, gitlab, slack and
     * text catalogs can be used in this direction.
     */
    public function toEmoji(string $catalog, string $text): string
    {
        return EmojiTransliterator::create($catalog, EmojiTransliterator::REVERSE)->transliterate($text);
    }
}
