<?php

namespace EmojiConverter;

use Symfony\Component\Emoji\EmojiTransliterator;

final class EmojiConverter
{
    // The catalogs that symfony/emoji can convert back to emoji. Keep in sync
    // with website/public/js/php.js
    private const SHORTCODE_CATALOGS = ['github', 'gitlab', 'slack', 'text'];

    // Some names contain non-breaking spaces ("drapeau : France" in French),
    // that nobody types
    private const SPACES = ["\u{A0}" => ' ', "\u{202F}" => ' '];

    // Everything that is not a letter or a digit separates words: spaces,
    // punctuation, but also apostrophes and hyphens, so "arc-en-ciel" is a run
    // of three words. Languages written without spaces (Japanese, Chinese…)
    // give long words, that only match a name as a whole.
    private const WORDS = <<<'REGEX'
        /
            (                   # Captured, so preg_split() returns the words too
                [\p{L}\p{N}]+   # Letters and digits of any script: "château", "東京", "100"
            )
        /ux
        REGEX;

    /**
     * Replaces emoji with their shortcode (github, gitlab, slack, text), their
     * name in a locale (en, fr, …), or removes them (strip).
     */
    public function fromEmoji(string $catalog, string $text): string
    {
        return EmojiTransliterator::create($catalog)->transliterate($text);
    }

    /**
     * Replaces shortcodes (github, gitlab, slack, text) or names in a locale
     * (en, fr, …) with their emoji.
     */
    public function toEmoji(string $catalog, string $text): string
    {
        if (\in_array($catalog, self::SHORTCODE_CATALOGS, true)) {
            return EmojiTransliterator::create($catalog, EmojiTransliterator::REVERSE)->transliterate($text);
        }

        return $this->namesToEmoji($catalog, $text);
    }

    /**
     * symfony/emoji does not convert names back to emoji: names are ordinary
     * words, replacing them with strtr() would also replace them inside other
     * words ("chat" in "achat"). So we replace whole words only, preferring the
     * longest name ("chat noir" over "chat").
     */
    private function namesToEmoji(string $catalog, string $text): string
    {
        // symfony/emoji does not expose its maps
        $map = (fn () => $this->map)->call(EmojiTransliterator::create($catalog));

        $emojiByName = [];
        foreach ($map as $emoji => $name) {
            $emojiByName[strtr($name, self::SPACES)] ??= $emoji;
        }
        $maxLength = max(array_map(\strlen(...), array_keys($emojiByName)));

        // Words at odd indexes, what separates them at even indexes. The keys
        // are the same parts, to look for in $emojiByName.
        $parts = preg_split(self::WORDS, $text, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $keys = preg_split(self::WORDS, strtr($text, self::SPACES), -1, \PREG_SPLIT_DELIM_CAPTURE);
        $count = \count($parts);

        $result = '';
        for ($i = 0; $i < $count; ++$i) {
            if (0 === $i % 2) {
                $result .= $parts[$i];

                continue;
            }

            // Grow a candidate from this word, part by part, as long as it can
            // still be a name
            $match = null;
            $candidate = '';
            // Names are lowercase, but the word may start a sentence
            $lowerCandidate = self::lowerFirst($keys[$i]);
            for ($j = $i; $j < $count && \strlen($candidate .= $keys[$j]) <= $maxLength; ++$j) {
                if ($j > $i) {
                    $lowerCandidate .= $keys[$j];
                }
                if (1 === $j % 2 && null !== $emoji = $emojiByName[$candidate] ?? $emojiByName[$lowerCandidate] ?? null) {
                    // Do not stop, so the last match is the longest name:
                    // "chat noir" overrides "chat"
                    $match = [$j, $emoji];
                }
            }

            if (null === $match) {
                $result .= $parts[$i];
            } else {
                [$i, $emoji] = $match;
                $result .= $emoji;
            }
        }

        return $result;
    }

    // mbstring is not available in the WebAssembly build
    private static function lowerFirst(string $word): string
    {
        preg_match(<<<'REGEX'
            /
                ^(?<first>.)    # The first character, not byte: "É" takes two
                (?<rest>.*)$
            /ux
            REGEX, $word, $matches);

        return \IntlChar::tolower($matches['first']) . $matches['rest'];
    }
}
