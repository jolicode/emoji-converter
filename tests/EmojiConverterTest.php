<?php

namespace EmojiConverter\Tests;

use EmojiConverter\EmojiConverter;
use EmojiConverter\UnknownShortcode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmojiConverter::class)]
class EmojiConverterTest extends TestCase
{
    public function testFromEmoji(): void
    {
        $converter = new EmojiConverter();

        $this->assertSame('a :cat:!!', $converter->fromEmoji('github', 'a 🐱!!'));
        $this->assertSame('a :+1::skin-tone-4:!!', $converter->fromEmoji('slack', 'a 👍🏽!!'));
        $this->assertSame('a tête de chat!!', $converter->fromEmoji('fr', 'a 🐱!!'));
        $this->assertSame('a !!', $converter->fromEmoji('strip', 'a 🐱!!'));
    }

    public function testToEmoji(): void
    {
        $converter = new EmojiConverter();

        $this->assertSame('a 🐱!!', $converter->toEmoji('github', 'a :cat:!!'));
        $this->assertSame('a 👍🏽!!', $converter->toEmoji('slack', 'a :+1::skin-tone-4:!!'));
    }

    public function testFindUnknownShortcodes(): void
    {
        $converter = new EmojiConverter();

        $this->assertEquals([
            new UnknownShortcode(':elephnt:', [':elephant:']),
            new UnknownShortcode(':Elephant:', [':elephant:']),
            new UnknownShortcode(':rocket_ship:', [':rocket:', ':rock:']),
            new UnknownShortcode(':thumbs:', [':thumbsup:', ':thumbsdown:']),
            new UnknownShortcode(':partyparrot:', []),
        ], $converter->findUnknownShortcodes('github', ':cat: :elephnt: :Elephant: :rocket_ship: :thumbs: :partyparrot: :elephnt:'));
        $this->assertEquals([
            new UnknownShortcode(':skin-tone-9:', [':skin-tone-2:', ':skin-tone-3:', ':skin-tone-4:']),
        ], $converter->findUnknownShortcodes('slack', ':+1::skin-tone-9:'));
        $this->assertSame([], $converter->findUnknownShortcodes('github', 'At 12:30:45, |:---:|, :elephant'));
        $this->assertSame([], $converter->findUnknownShortcodes('fr', ':elephnt:'));
    }

    public function testBadCatalog(): void
    {
        $converter = new EmojiConverter();

        $this->expectException(\IntlException::class);

        $converter->fromEmoji('team', 'a 🐱!!');
    }

    public function testToEmojiWithNames(): void
    {
        $converter = new EmojiConverter();

        $this->assertSame('a 🐱!!', $converter->toEmoji('fr', 'a tête de chat!!'));
        $this->assertSame('Un 🐈‍⬛ au 🏰, pas un achat', $converter->toEmoji('fr', 'Un chat noir au château, pas un achat'));
        $this->assertSame('🐘 et 🐈', $converter->toEmoji('fr', 'Éléphant et Chat'));
        $this->assertSame('👍🏽 🇫🇷', $converter->toEmoji('fr', 'pouce vers le haut : peau légèrement mate drapeau : France'));
        $this->assertSame('a 🐱 🐈', $converter->toEmoji('en', 'a cat face cat'));
    }

    public function testToEmojiWithStrip(): void
    {
        $converter = new EmojiConverter();

        $this->assertSame('a cat', $converter->toEmoji('strip', 'a cat'));
    }
}
