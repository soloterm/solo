<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit\Screen;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Tests\Support\ComparesVisually;
use SoloTerm\Solo\Tests\Unit\Base;

class MultibyteTest extends Base
{
    use ComparesVisually;

    #[Test]
    public function width_tests()
    {
        $this->assertTerminalMatch('a' . "\u{200D}" . "\u{0301}" . 'b');
        $this->assertTerminalMatch([
            '텍' . "\u{FE0E}",
            "\e[1;3Habc"
        ], iterate: true);

    }

    #[Test]
    public function test_wide_character_overwrite(): void
    {
        $this->assertTerminalMatch('ab文字cd' . "\e[1G\e[2C***");
        $this->assertTerminalMatch('ab文字cd' . "\e[1G\e[3C***");
        $this->assertTerminalMatch('ab文字cd' . "\e[1G\e[4C***");
    }

    #[Test]
    public function test_multibyte_character_overwrite_with_single_byte(): void
    {
        $this->assertTerminalMatch("ASCII文字Mixed文字\e[6Dabc");
        $this->assertTerminalMatch("ASCII文字Mixed文字\e[5Dabc");
        $this->assertTerminalMatch("ASCII文字Mixed文字\e[4Dabc");
    }

    #[Test]
    public function test_multibyte_character_overwrite_with_multi_byte(): void
    {
        $this->assertTerminalMatch("ASCII文文Mixed文文\e[6D字");
        $this->assertTerminalMatch("ASCII文字Mixed文字\e[5D字");
        $this->assertTerminalMatch("ASCII文字Mixed文字\e[4D字");
    }

    #[Test]
    public function emoji_overwrite()
    {
        $this->assertTerminalMatch("abcdefg\e[1;2H" . '🙂');
        $this->assertTerminalMatch("abcdefg\e[1;2H" . '🐛');
        $this->assertTerminalMatch("abcdefg\e[1;2H" . '❤️');
        $this->assertTerminalMatch("abcdefg\e[1;2H" . '🇺🇸');
    }

    #[Test]
    public function emoji_overwrite_ansi()
    {
        $this->assertTerminalMatch("\e[31mabcdefg\e[0m\e[1;2H" . '🙂');
        $this->assertTerminalMatch("\e[31mabcdefg\e[0m\e[1;2H" . '🐛');
        $this->assertTerminalMatch("\e[31mabcdefg\e[0m\e[1;2H" . '❤️');
        $this->assertTerminalMatch("\e[31mabcdefg\e[0m\e[1;2H" . '🇺🇸');
    }

    #[Test]
    public function emoji_overflow()
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = str_repeat('-', $width);

        $this->assertTerminalMatch($full . "\e[1;5H" . '🙂');
        $this->assertTerminalMatch($full . "\e[1;5H" . '🐛');
        $this->assertTerminalMatch($full . "\e[1;5H" . '❤️');
        $this->assertTerminalMatch($full . "\e[1;5H" . '🇺🇸');
    }

    #[Test]
    public function emoji_overflow_ansi()
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = str_repeat('-', $width);

        // 1 char, 3 bytes
        $this->assertTerminalMatch("\e[31m" . $full . "\e[0m\e[1;5H" . '🙂');
        $this->assertTerminalMatch("\e[31m" . $full . "\e[0m\e[1;5H" . '🐛');
        $this->assertTerminalMatch("\e[31m" . $full . "\e[0m\e[1;5H" . '❤️');
        $this->assertTerminalMatch("\e[31m" . $full . "\e[0m\e[1;5H" . '🇺🇸');
    }

    protected function emojiBefore($emoji)
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));
        $this->assertTerminalMatch($full . "\e[;5H aaron ");
    }

    #[Test]
    public function emoji_before()
    {
        $this->emojiBefore('🙂');
        $this->emojiBefore('🐛');
        $this->emojiBefore('❤️');
        $this->emojiBefore('🇺🇸');
    }

    protected function emojiBeforeAnsi($emoji)
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));
        $this->assertTerminalMatch("\e[33m" . $full . "\e[0m\e[;5Haaron");
    }

    #[Test]
    public function emoji_before_ansi()
    {
        $this->emojiBeforeAnsi('🙂');
        $this->emojiBeforeAnsi('🐛');
        $this->emojiBeforeAnsi('❤️');
        $this->emojiBeforeAnsi('🇺🇸');
    }

    #[Test]
    public function emoji_extend_line()
    {
        $this->assertTerminalMatch('🙂' . "asdf\e[;15H aaron ");
        $this->assertTerminalMatch('🐛' . "asdf\e[;15H aaron ");
        $this->assertTerminalMatch('❤️' . "asdf\e[;15H aaron ");
        $this->assertTerminalMatch('🇺🇸' . "asdf\e[;15H aaron ");
    }

    #[Test]
    public function grapheme_splice()
    {
        $this->assertTerminalMatch('🙂' . "a\e[2D.\n..");
        $this->assertTerminalMatch('🐛' . "a\e[2D.\n..");
        $this->assertTerminalMatch('❤️' . "a\e[2D.\n..");
        $this->assertTerminalMatch('🇺🇸' . "a\e[2D.\n..");
    }

    protected function cursorEndsInTheRightSpot($emoji)
    {
        $this->assertTerminalMatch([
            '--------------------------',
            "\e[15D",
            $emoji,
            'test'
        ], iterate: true);
    }

    #[Test]
    public function cursor_ends_in_the_right_spot()
    {
        $this->cursorEndsInTheRightSpot('🙂');
        $this->cursorEndsInTheRightSpot('🐛');
        $this->cursorEndsInTheRightSpot('❤️');
        $this->cursorEndsInTheRightSpot('🇺🇸');
    }

    #[Test]
    public function test_combining_characters(): void
    {
        // Testing characters with combining diacritical marks
        $this->assertTerminalMatch("e\u{0301}" . "abc\e[1G\e[2C***"); // é (e + combining acute)
        $this->assertTerminalMatch("a\u{0308}" . "abc\e[1G\e[2C***"); // ä (a + combining diaeresis)
        $this->assertTerminalMatch("n\u{0303}" . "abc\e[1G\e[2C***"); // ñ (n + combining tilde)
    }

    #[Test]
    public function test_right_to_left_text(): void
    {
        // Testing right-to-left text (Arabic, Hebrew)
        $this->assertTerminalMatch("abc\u{0644}\u{0645}\u{0631}\u{062D}\u{0628}\u{0627}def\e[5G***"); // مرحبا (hello in Arabic)
        $this->assertTerminalMatch("abc\u{05E9}\u{05DC}\u{05D5}\u{05DD}def\e[5G***"); // שלום (peace in Hebrew)
    }

    #[Test]
    public function test_zero_width_joiners(): void
    {
        // @TODO HERE
        // Testing zero-width joiners and their effect
        $this->assertTerminalMatch("abc\u{200D}def\e[1G\e[3C***"); // Zero-width joiner
        $this->assertTerminalMatch("👨\u{200D}👩\u{200D}👧\u{200D}👦xyz\e[1G\e[2C***"); // Family emoji with ZWJ
        $this->assertTerminalMatch("👩\u{200D}💻abc\e[1G\e[2C***"); // Woman technologist
    }

    #[Test]
    public function test_complex_script_overwrite(): void
    {
        // Testing complex scripts (Thai, Devanagari)
        $this->assertTerminalMatch("abc\u{0E2A}\u{0E27}\u{0E31}\u{0E2A}\u{0E14}\u{0E35}def\e[4D***"); // สวัสดี (hello in Thai)
        $this->assertTerminalMatch("abc\u{0928}\u{092E}\u{0938}\u{094D}\u{0924}\u{0947}def\e[4D***"); // नमस्ते (hello in Hindi)
    }

    #[Test]
    public function test_tab_with_multibyte(): void
    {
        // Testing tab behavior with multibyte characters
        $this->assertTerminalMatch("文字\t文字\e[1G\e[4C***");
        $this->assertTerminalMatch("🙂\t文字\e[1G\e[3C***");
        $this->assertTerminalMatch("abc\t文字\e[1G\e[5C***");
    }

    #[Test]
    public function test_line_wrapping_with_multibyte(): void
    {
        $width = $this->makeIdenticalScreen()->width;
        $padding = $width - 5; // Leave space for 5 characters

        // Test line wrapping with multibyte at the edge
        $this->assertTerminalMatch(str_repeat('-', $padding) . "文字文\e[1G\e[" . ($width - 1) . 'C*');
        $this->assertTerminalMatch(str_repeat('-', $padding) . "🙂🙂\e[1G\e[" . ($width - 1) . 'C*');
    }

    #[Test]
    public function test_backspace_with_multibyte(): void
    {
        // Simulating backspace behavior with multibyte characters
        $this->assertTerminalMatch("abc文字\e[1D \e[1D"); // Backspace over 文
        $this->assertTerminalMatch("abc🙂\e[1D \e[1D"); // Backspace over 🙂
        $this->assertTerminalMatch("abc❤️\e[1D \e[1D"); // Backspace over ❤️
    }

    #[Test]
    public function test_cursor_movement_in_multibyte_strings(): void
    {
        // Test cursor movement with arrow keys in multibyte strings
        $this->assertTerminalMatch("abc文字def\e[1G\e[C\e[C\e[C\e[C***"); // Move right 4 times
        $this->assertTerminalMatch("abc🙂def\e[1G\e[C\e[C\e[C\e[C***"); // Move right 4 times
        $this->assertTerminalMatch("abc文字def\e[1G\e[7C\e[D\e[D\e[D***"); // Move left 3 times
    }

    #[Test]
    public function test_mixed_width_characters(): void
    {
        // Testing mixed full-width, half-width and emoji characters
        $this->assertTerminalMatch("ａｂｃ123文字🙂\e[1G\e[4C***");
        $this->assertTerminalMatch("abc１２３文字🙂\e[1G\e[4C***");
        $this->assertTerminalMatch("ａｂｃ１２３文字🙂\e[1G\e[5C***");
    }

    #[Test]
    public function test_complex_emoji_sequences(): void
    {
        // @TODO HERE
        // Testing complex emoji sequences and modifiers
        $this->assertTerminalMatch("abc👨🏽‍💻def\e[5D***"); // Man technologist with skin tone
        $this->assertTerminalMatch("abc🏳️‍🌈def\e[5D***"); // Rainbow flag
        $this->assertTerminalMatch("abc👨‍👩‍👧‍👦def\e[5D***"); // Family emoji
        $this->assertTerminalMatch("abc🧑🏻‍🤝‍🧑🏿def\e[5D***"); // People holding hands with different skin tones
    }
}
