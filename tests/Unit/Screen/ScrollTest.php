<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace SoloTerm\Solo\Tests\Unit\Screen;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Tests\Support\ComparesVisually;
use SoloTerm\Solo\Tests\Unit\Base;

class ScrollTest extends Base
{
    use ComparesVisually;

    #[Test]
    public function test_scroll_down_1(): void
    {
        $this->assertTerminalMatch([
            implode(PHP_EOL, range(0, 100)),
            "\e[2T",
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_2(): void
    {
        $height = $this->makeIdenticalScreen()->height;

        $this->assertTerminalMatch([
            implode(PHP_EOL, range(0, $height - 5)),
            "\e[2T",
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_3(): void
    {
        $height = $this->makeIdenticalScreen()->height;

        $this->assertTerminalMatch([
            'Old',
            "\e[2T",
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_with_colored_content(): void
    {
        $this->assertTerminalMatch([
            "\e[31mLine 1\e[0m\n\e[32mLine 2\e[0m\n\e[33mLine 3\e[0m",
            "\e[3T",  // Scroll down 3 lines
            "\e[1;1H\e[34mNew Colored Content\e[0m"
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_at_bottom_boundary(): void
    {
        $height = $this->makeIdenticalScreen()->height;

        $this->assertTerminalMatch([
            implode(PHP_EOL, range(1, $height)),
            "\e[$height;1H\e[5T",  // Move to last line and scroll down 5
            'Bottom Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_with_cursor_preservation(): void
    {
        $this->assertTerminalMatch([
            "Line 1\nLine 2\nLine 3\nLine 4\nLine 5",
            "\e[3;4H",  // Position cursor at line 3, column 4
            "\e[2T",    // Scroll down 2 lines
            'X'         // Should appear at line 3, column 4
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_with_wrapped_lines(): void
    {
        $longText = str_repeat('-.-', 200);

        $this->assertTerminalMatch([
            $longText,
            "\e[3T",  // Scroll down 3 lines
            "\e[1;1HNew Content After Scroll"
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_large_value(): void
    {
        $this->assertTerminalMatch([
            implode(PHP_EOL, range(1, 10)),
            "\e[100T",  // Scroll down more lines than available
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_down_zero_value(): void
    {
        $this->assertTerminalMatch([
            "Line 1\nLine 2\nLine 3",
            "\e[0T",  // Scroll down 0 lines (should do nothing)
            'Unchanged Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_basic(): void
    {
        $this->assertTerminalMatch([
            implode(PHP_EOL, range(0, 100)),
            "\e[2S",  // Scroll up 2 lines
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_near_buffer_limit(): void
    {
        $height = $this->makeIdenticalScreen()->height;

        $this->assertTerminalMatch([
            implode(PHP_EOL, range(0, $height - 5)),
            "\e[2S",  // Scroll up 2 lines
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_minimal_content(): void
    {
        $this->assertTerminalMatch([
            'Old',
            "\e[2S",  // Scroll up 2 lines
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_with_colored_content(): void
    {
        $this->assertTerminalMatch([
            "\e[31mLine 1\e[0m\n\e[32mLine 2\e[0m\n\e[33mLine 3\e[0m",
            "\e[3S",  // Scroll up 3 lines
            "\e[1;1H\e[34mNew Colored Content\e[0m"
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_at_top_boundary(): void
    {
        $this->assertTerminalMatch([
            implode(PHP_EOL, range(1, 10)),
            "\e[1;1H\e[5S",  // Move to top line and scroll up 5
            'Top Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_with_cursor_preservation(): void
    {
        $this->assertTerminalMatch([
            "Line 1\nLine 2\nLine 3\nLine 4\nLine 5",
            "\e[3;4H",  // Position cursor at line 3, column 4
            "\e[2S",    // Scroll up 2 lines
            'X'         // Should appear at line 3, column 4
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_with_wrapped_lines(): void
    {
        $longText = str_repeat('-.-', 200);

        $this->assertTerminalMatch([
            $longText,
            "\e[3S",  // Scroll up 3 lines
            "\e[1;1HNew Content After Scroll"
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_large_value(): void
    {
        $this->assertTerminalMatch([
            implode(PHP_EOL, range(1, 10)),
            "\e[100S",  // Scroll up more lines than available
            'New Content'
        ], iterate: true);
    }

    #[Test]
    public function test_scroll_up_zero_value(): void
    {
        $this->assertTerminalMatch([
            "Line 1\nLine 2\nLine 3",
            "\e[0S",  // Scroll up 0 lines (should do nothing)
            'Unchanged Content'
        ], iterate: true);
    }
}
