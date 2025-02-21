<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use Generator;
use Laravel\Prompts\Terminal;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Tests\Support\ComparesVisually;

use function Orchestra\Testbench\package_path;

class EmojiTest extends Base
{
    use ComparesVisually;

    #[Test]
    public function single_char_emoji_overwrite()
    {
        $this->assertTerminalMatch("abcdefg\e[1;2H🐛");
    }

    #[Test]
    public function single_char_emoji_overwrite_ansi()
    {
        $this->assertTerminalMatch("\e[31mabcdefg\e[0m\e[1;2H🐛");
    }

    #[Test]
    public function double_char_emoji_overwrite()
    {
        $this->assertTerminalMatch("abcdefg\e[1;2H❤️");
    }

    #[Test]
    public function double_char_emoji_overwrite_ansi()
    {
        $this->assertTerminalMatch("a\e[31mbcdefg\e[0m\e[1;2H❤️");
    }

    #[Test]
    public function single_char_emoji_overflow()
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = str_repeat('-', $width);

        // 1 char, 3 bytes
        $this->assertTerminalMatch($full . "\e[1;5H🐛");
    }

    #[Test]
    public function single_char_emoji_overflow_ansi()
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = str_repeat('-', $width);

        // 1 char, 3 bytes
        $this->assertTerminalMatch("\e[31m" . $full . "\e[0m\e[1;5H🐛");
    }

    #[Test]
    public function double_char_emoji_overflow()
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = str_repeat('-', $width);

        // 2 chars, 6 bytes
        $this->assertTerminalMatch($full . "\e[1;5H❤️");
    }

    #[Test]
    public function double_char_emoji_overflow_ansi()
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = str_repeat('-', $width);

        // 1 char, 3 bytes
        $this->assertTerminalMatch("\e[31m" . $full . "\e[0m\e[1;5H❤️");
    }

    #[Test]
    public function single_char_emoji_before()
    {
        $width = $this->makeIdenticalScreen()->width;

        $emoji = '🐛';
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));

        $this->assertTerminalMatch($full . "\e[;5H aaron ");
    }

    #[Test]
    public function single_char_emoji_before_ansi()
    {
        $width = $this->makeIdenticalScreen()->width;

        $emoji = '🐛';
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));

        $this->assertTerminalMatch("\e[33m" . $full . "\e[0m\e[;5H aaron ");
    }


    #[Test]
    public function double_char_emoji_before()
    {
        $width = $this->makeIdenticalScreen()->width;

        $emoji = '❤️';
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));

        $this->assertTerminalMatch($full . "\e[;5H aaron ");
    }

    #[Test]
    public function double_char_emoji_before_ansi()
    {
        $width = $this->makeIdenticalScreen()->width;

        $emoji = '❤️';
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));

        $this->assertTerminalMatch("\e[33m" . $full . "\e[0m\e[;5H aaron ");
    }

    #[Test]
    public function single_char_emoji_extend_line()
    {
        $this->assertTerminalMatch("🐛asdf\e[;15H aaron ");
    }

    public function double_char_emoji_before_extend_line()
    {
        $this->assertTerminalMatch("❤️asdf\e[;15H aaron ");
    }

    #[Test]
    public function proof_double_col()
    {
        $this->assertTerminalMatch("❤️a\n..a");
    }

    #[Test]
    public function grapheme_splice()
    {
        $this->assertTerminalMatch("❤️a\e[2D.\n..");
    }

    #[Test]
    public function single_char_cursor_ends_in_the_right_spot()
    {
        $this->assertTerminalMatch([
            '--------------------------',
            "\e[15D",
            "🐛️",
            'test'
        ], iterate: true);
    }

    #[Test]
    public function double_char_cursor_ends_in_the_right_spot()
    {
        $this->assertTerminalMatch([
            '--------------------------',
            "\e[15D",
            "❤️",
            'test'
        ], iterate: true);
    }


}
