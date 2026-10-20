<?php

namespace EmojiConverter\Tests;

use EmojiConverter\EmojiConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmojiConverter::class)]
class EmojiConverterTest extends TestCase
{
    public function testFromEmoji()
    {
        $converter = new EmojiConverter();

        $this->assertSame('a :cat:!!', $converter->fromEmoji('github', 'a 🐱!!'));
        $this->assertSame('a :+1::skin-tone-4:!!', $converter->fromEmoji('slack', 'a 👍🏽!!'));
        $this->assertSame('a tête de chat!!', $converter->fromEmoji('fr', 'a 🐱!!'));
        $this->assertSame('a !!', $converter->fromEmoji('strip', 'a 🐱!!'));
    }

    public function testToEmoji()
    {
        $converter = new EmojiConverter();

        $this->assertSame('a 🐱!!', $converter->toEmoji('github', 'a :cat:!!'));
        $this->assertSame('a 👍🏽!!', $converter->toEmoji('slack', 'a :+1::skin-tone-4:!!'));
    }

    public function testBadCatalog()
    {
        $converter = new EmojiConverter();

        $this->expectException(\IntlException::class);

        $converter->fromEmoji('team', 'a 🐱!!');
    }

    public function testToEmojiWithLocale()
    {
        $converter = new EmojiConverter();

        $this->expectException(\IntlException::class);

        $converter->toEmoji('fr', 'a tête de chat!!');
    }
}
