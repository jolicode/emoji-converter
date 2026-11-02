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

    // What toEmoji() leaves of a shortcode it does not know. The shortcodes of
    // all the catalogs use these characters only.
    private const SHORTCODES = <<<'REGEX'
        /
            (?<![\p{L}\p{N}])       # Not inside a word or a number: "12:30:45"
            :
            (?=[_+-]*[a-z0-9])      # A letter or a digit: not the ":---:" of Markdown tables
            [a-z0-9_+-]+
            :
        /uix
        REGEX;

    private const MAX_SUGGESTIONS = 3;

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
     * Finds the shortcodes that toEmoji() does not know, with the known ones
     * that look like them: ":elephnt:" is probably ":elephant:".
     *
     * @return list<UnknownShortcode>
     */
    public function findUnknownShortcodes(string $catalog, string $text): array
    {
        // Names are ordinary words: a typo is just another word
        if (!\in_array($catalog, self::SHORTCODE_CATALOGS, true)) {
            return [];
        }

        // Once the known shortcodes are converted, even the unknown ones stuck
        // to a known one stand out: ":+1::skin-tone-9:"
        preg_match_all(self::SHORTCODES, $this->toEmoji($catalog, $text), $matches);
        $shortcodes = array_keys(self::map(EmojiTransliterator::create($catalog, EmojiTransliterator::REVERSE)));

        return array_map(
            static fn (string $unknown) => new UnknownShortcode($unknown, self::suggestShortcodes($unknown, $shortcodes)),
            array_values(array_unique($matches[0])),
        );
    }

    /**
     * symfony/emoji does not convert names back to emoji: names are ordinary
     * words, replacing them with strtr() would also replace them inside other
     * words ("chat" in "achat"). So we replace whole words only, preferring the
     * longest name ("chat noir" over "chat").
     */
    private function namesToEmoji(string $catalog, string $text): string
    {
        $emojiByName = [];
        foreach (self::map(EmojiTransliterator::create($catalog)) as $emoji => $name) {
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

    /**
     * A typo (":elephnt:"), or the beginning or the end missing
     * (":rocket_ship:" for ":rocket:", ":thumbs:" for ":thumbsup:").
     *
     * @param list<string> $shortcodes
     *
     * @return list<string>
     */
    private static function suggestShortcodes(string $unknown, array $shortcodes): array
    {
        $name = strtolower(trim($unknown, ':'));
        // One typo in a short name, more in a long one: one typo away, "lol"
        // is already "cl" or "bow"
        $maxDistance = max(1, intdiv(\strlen($name), 4));

        $scores = [];
        foreach ($shortcodes as $shortcode) {
            $candidate = trim($shortcode, ':');
            $distance = levenshtein($name, $candidate);
            if ($distance > $maxDistance && !self::isPrefixOfTheOther($name, $candidate)) {
                continue;
            }

            // At the same distance, "hart" is more "heart" than "art"
            similar_text($name, $candidate, $similarity);
            $scores[$shortcode] = [$distance, -$similarity];
        }
        asort($scores);

        return \array_slice(array_keys($scores), 0, self::MAX_SUGGESTIONS);
    }

    private static function isPrefixOfTheOther(string $a, string $b): bool
    {
        [$short, $long] = \strlen($a) < \strlen($b) ? [$a, $b] : [$b, $a];

        // "a" or "ok" would be a prefix of too many names
        return \strlen($short) >= 3 && str_starts_with($long, $short);
    }

    /**
     * @return array<string, string>
     */
    private static function map(EmojiTransliterator $transliterator): array
    {
        // symfony/emoji does not expose its maps
        return (fn () => $this->map)->call($transliterator);
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
