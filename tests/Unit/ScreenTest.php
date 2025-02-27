<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Support\Screen;
use SoloTerm\Solo\Tests\Support\ComparesVisually;

class ScreenTest extends Base
{
    use ComparesVisually;

    #[Test]
    public function clear_screen_test()
    {
        $this->assertTerminalMatch([
            'Hello World',
            'Hello World',
            "\e[2J",
            'New Line after clear'
        ]);
    }

    #[Test]
    public function colors_are_preserved()
    {
        $this->assertTerminalMatch("Hello \e[32mWorld!\e[39m how are you");
    }

    #[Test]
    public function laravel_prompts_make_model(): void
    {
        $this->assertTerminalMatch([
            "\e[?25l",
            "\e[90m ┌\e[39m \e[36mWhat should the model be named?\e[39m \e[90m─────────────────────────────┐\e[39m",
            "\e[90m │\e[39m \e[2m\e[7mE\e[27m.g. Flight\e[22m                                                  \e[90m│\e[39m",
            "\e[90m └──────────────────────────────────────────────────────────────┘\e[39m",
            '',
            '',
        ]);
    }

    #[Test]
    public function test_simple_line_without_ansi_codes(): void
    {
        $this->assertTerminalMatch([
            'Hello, World!',
            '',
            'Part two!'
        ]);
    }

    #[Test]
    public function single_line_ansi_colors(): void
    {
        $this->assertTerminalMatch("\e[31mRed Text\e[0m");
    }

    #[Test]
    public function test_cursor_horizontal_absolute(): void
    {
        $this->assertTerminalMatch([
            'Start',
            "\e[5GHello",
        ]);
    }

    #[Test]
    public function test_cursor_forward(): void
    {
        $this->assertTerminalMatch([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            "Line 3: Goodbye!\e[5CForward",
        ]);
    }

    public function test_cursor_backward(): void
    {
        $this->assertTerminalMatch([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            "Line 3: Goodbye!\e[3DBack",
        ]);
    }

    #[Test]
    public function test_cursor_home(): void
    {
        $this->assertTerminalMatch([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: Goodbye!',
            "\e[HHome", // Move cursor to home position and insert "Home"
        ]);
    }

    #[Test]
    public function test_cursor_up(): void
    {
        $this->assertTerminalMatch([
            'Line 1',
            'Line 2',
            'Line 3',
            "\e[2AInserted Line",
        ]);
    }

    #[Test]
    public function test_erase_display_from_cursor_to_end(): void
    {
        $this->assertTerminalMatch([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            "Line 3: Goodbye!\e[2G\e[0J",
        ]);
    }

    #[Test]
    public function test_erase_display_from_start_to_cursor(): void
    {
        $this->assertTerminalMatch([
            'Line 1: Hello, World!',
            "Line 2: This is a test.\e[10G\e[1J",
            'Line 3: Goodbye!',
        ]);
    }

    #[Test]
    public function test_show_and_hide_cursor(): void
    {
        $this->assertTerminalMatch([
            "\e[?25l",
            'Hidden Cursor Line',
        ]);
    }

    #[Test]
    public function test_combined_ansi_codes(): void
    {
        $this->assertTerminalMatch([
            'Line 1',
            'Line 2',
            'Line 3',
            "\e[1A\e[5GInserted"
        ]);
    }

    #[Test]
    public function test_move_down_ansi_code(): void
    {
        $this->assertTerminalMatch([
            'Line 1: Hello, World!',
            'Line 2: This is a test.',
            'Line 3: Goodbye!',
            "\e[1B\e[5GInserted Line"
        ]);
    }

    #[Test]
    public function test_cursor_movement_beyond_screen_buffer(): void
    {
        $this->assertTerminalMatch([
            'Line 1',
            "\e[5AAbove Start"
        ]);
    }

    #[Test]
    public function test_erase_in_line_1(): void
    {
        $this->assertTerminalMatch("hello world\e[4G\e[1K");
    }

    #[Test]
    public function test_erase_in_line_2(): void
    {
        $this->assertTerminalMatch("hello world\e[4G\e[2K");
    }

    #[Test]
    public function test_erase_in_line_3(): void
    {
        $this->assertTerminalMatch("hello world\e[4G\e[0K");
    }

    #[Test]
    public function test_cursor_with_colors(): void
    {
        $this->assertTerminalMatch("\e[1;32mWorld\e[0m Test\e[8Dq");
    }

    #[Test]
    public function test_cursor_delete_with_colors(): void
    {
        $this->assertTerminalMatch("\e[1;32mWorld\e[0m Test\e[11G\e[1Ktest");
    }

    #[Test]
    public function clear_screen_from_cursor_to_end_clears_buffer(): void
    {
        $this->assertTerminalMatch([
            "\e[34m",
            'abcd',
            'efgh',
            'ijkl',
            "\e[2A\e[1C\e[0J@"
        ]);
    }

    #[Test]
    public function clear_screen_from_start_to_cursor_clears_buffer(): void
    {
        $this->assertTerminalMatch([
            "\e[34m",
            'abcd',
            'efgh',
            'ijkl',
            "\e[2A\e[2C\e[1J@"
        ]);
    }

    #[Test]
    public function clear_entire_screen_clears_buffer(): void
    {
        $this->assertTerminalMatch([
            "\e[34m",
            'abcd',
            'efgh',
            'ijkl',
            "\e[2A\e[2C\e[2J"
        ]);
    }

    #[Test]
    public function erase_in_line_0(): void
    {
        $this->assertTerminalMatch([
            "\e[34m",
            'abcde',
            "\e[1A\e[2C\e[0K",
            '@'
        ]);
    }

    #[Test]
    public function erase_in_line_1(): void
    {
        $this->assertTerminalMatch([
            "\e[34m",
            'abcde',
            "\e[1A\e[2C\e[1K@"
        ]);
    }

    #[Test]
    public function erase_in_line_2(): void
    {
        $this->assertTerminalMatch([
            "\e[34m",
            'abcde',
            "\e[1A\e[2C\e[2K@"
        ]);
    }

    #[Test]
    public function save_and_restore_cursor(): void
    {
        $this->assertTerminalMatch("\e7this is a test\e8haha!");
    }

    #[Test]
    public function basic_move_test()
    {
        $this->assertTerminalMatch("Test\e[1000DBar ");
    }

    #[Test]
    public function basic_writeln_test_1()
    {
        $screen = new Screen(180, 30);

        // No trailing newline
        $screen->write('Test');
        $screen->writeln('New');

        $this->assertEquals("Test\nNew\n", $screen->output());
    }

    #[Test]
    public function basic_writeln_test_2()
    {
        $screen = new Screen(180, 30);

        // Trailing newline
        $screen->write("Test\n");
        $screen->writeln('New');

        $this->assertEquals("Test\nNew\n", $screen->output());
    }

    #[Test]
    public function simple_wrap()
    {
        $this->assertTerminalMatch(implode('', range(1, 200)));
    }

    #[Test]
    public function wrap_overwrite()
    {
        $this->assertTerminalMatch([
            '',
            implode('', range(1, 200)) . "\e[1F--overwritten--",
            '345'
        ]);
    }

    #[Test]
    public function move_up_constrained_test()
    {
        $this->assertTerminalMatch("Test\e[1000FBar ");
    }

    #[Test]
    public function carriage_return_test()
    {
        $this->assertTerminalMatch("Test\rBar ");
    }

    #[Test]
    public function newline_test()
    {
        $this->assertTerminalMatch("Test\nBar");
    }

    #[Test]
    public function trailing_newlines()
    {
        $screen = new Screen(180, 30);
        $screen->write("Test\n\n");
        // Can't see trailing newlines, so test the output directly
        $this->assertEquals("Test\n\n", $screen->output());
    }

    #[Test]
    public function cursor_remains_in_correct_location()
    {
        $this->assertTerminalMatch("Test\n\n\e[5CBuzz");
    }

    #[Test]
    public function move_forward_and_write()
    {
        $this->assertTerminalMatch("1\e[5C23");
    }

    #[Test]
    public function doesnt_go_past_width_relative()
    {
        $this->assertTerminalMatch("\e[1000Ca");
    }

    #[Test]
    public function doesnt_go_past_width_absolute()
    {
        $this->assertTerminalMatch("\e[1000Ga");
    }

    #[Test]
    public function doesnt_go_past_height_relative()
    {
        $this->assertTerminalMatch([
            ...range(1, 100),
            "101\e[1000A12"
        ]);
    }

    #[Test]
    public function issue_found_while_creating_popup4()
    {
        $this->assertTerminalMatch("\e[0;31m red red \e[4D\e[32mgreen\e[2m dimmmm");
    }

    #[Test]
    public function issue_found_while_creating_popup3()
    {
        $this->assertTerminalMatch("\e[0;31m red red \e[4D\e[0mblack\e[2m dimmmm");
    }

    #[Test]
    public function issue_found_while_creating_popup2()
    {
        $this->assertTerminalMatch([
            "\e[0;2;49mRunning: tail -f -n 100 /Users/",
            'Running: tail -f -n 100 /Users/',
            "\e[1A\e[1C\e[0mZZZZZZZZ",
        ]);
    }

    #[Test]
    public function issue_found_while_creating_popup1()
    {
        $this->assertTerminalMatch([
            "\e[H\e[2mRunning",
            'Running',
            'Running',
            'Running',
            'Running',
            "\e[4A\e[4C\e[103m\nTest",
        ]);
    }

    #[Test]
    public function doesnt_go_past_height_home()
    {
        $this->assertTerminalMatch([
            ...range(1, 100),
            "\e[H12"
        ]);
    }

    #[Test]
    public function clear_up_doesnt_go_off_screen()
    {
        $this->assertTerminalMatch([
            ...range(1, 100),
            "\e[100A\e[1J",
        ]);
    }

    #[Test]
    public function clear_down()
    {
        $this->assertTerminalMatch([
            ...range(1, 100),
            "\e[5A\e[0J"
        ]);
    }

    #[Test]
    public function clear_doesnt_go_off_screen()
    {
        $screen = new Screen(180, 10);

        $screen->write("1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11");
        $screen->write("\e[2J");

        $this->assertEquals(
            '1









',
            $screen->output()
        );
    }

    #[Test]
    public function stash_restore_off_screen()
    {
        $this->assertTerminalMatch([
            ...range(1, 100),
            "101\e[1000A\e7\e[1000B\e8aaa",
        ]);
    }

    #[Test]
    public function alt_screen()
    {
        $this->markTestSkipped('Not implemented yet');
        $this->assertTerminalMatch("abcd\e[?1049hefgh");
    }

    #[Test]
    public function about_command()
    {
        // This is a bug from the solo:about command when rendered inside of our content pane.
        $this->assertTerminalMatch("\e[22m\e[2mdim");
    }

    #[Test]
    public function renderer()
    {
        $content = [
            '┌────────────────────────────────────┓',
            '│                                    │',
            '│                                    │',
            '│                                    │',
            '│                                    │',
            '│                                    │',
            '│                                    │',
            '└━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛',
            "\e[H\e[1B\e[2C\e[102mThis should have",
            "\e[2Ca background"
        ];

        $this->assertTerminalMatch($content);
    }

    #[Test]
    public function extended_color_id()
    {
        $output = "\e[0;38;5;208m\"\e[1;38;5;113myo\e[0;38;5;208m\"\e[m \e[90m// \e[39m\e[90m/Users/aaron/Code/solo/src/Console/Commands/About.php:24\e[39m";

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function extended_color_id_with_reset()
    {
        $output = "\e[0;38;5;208m\"\e[1;38;5;113myo \e[39mwhats \e[0mgood";

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function extended_color_id_with_reset_all()
    {
        $output = "\e[0;38;5;208m\"\e[0myo \e[39mwhats \e[0mgood";

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function extended_color_id_with_malformed_id()
    {
        $output = "\e[0;38;5mhey this is bad";

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function extended_color_id_with_decoration()
    {
        $output = "\e[38;5;208;4m\"\e[1;38;5;113myo \e[39mwhats \e[0mgood";

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function extended_color_rbg()
    {
        $output = "\e[0;38;2;255;0;255mHey hows it going";

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function extended_color_rbg_malformed()
    {
        $output = "\e[0;38;2;255;0mHey hows it going";

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function about_dumps_wrong_color()
    {
        $content = <<<TXT
\e[32mInfo!\e[39m
\e[32mInfo!\e[39m
TXT;

        $expected = <<<TXT
\e[32mInfo!
\e[32mInfo!
TXT;

        $actual = $this->makeIdenticalScreen()->write($content)->output();

        $this->assertEquals($expected, $actual);

    }

    #[Test]
    public function capturing_prompts_didnt_work()
    {
        $output = <<<TXT
\e[?25l
\e[90m ┌\e[39m \e[36mPick a command\e[39m \e[90m──────────────────────────────────────────────┐\e[39m
\e[90m │\e[39m \e[36m›\e[39m ◻ About                                                    \e[90m│\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mLogs\e[22m                                                     \e[90m│\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mVite\e[22m                                                     \e[90m│\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mReverb\e[22m                                                   \e[90m│\e[39m
\e[90m └──────────────────────────────────────────────────────────────┘\e[39m

\e[1G\e[8A\e[J
\e[90m ┌\e[39m \e[36mPick a command\e[39m \e[90m──────────────────────────────────────────────┐\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mAbout\e[22m                                                    \e[90m│\e[39m
\e[90m │\e[39m \e[36m›\e[39m ◻ Logs                                                     \e[90m│\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mVite\e[22m                                                     \e[90m│\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mReverb\e[22m                                                   \e[90m│\e[39m
\e[90m └──────────────────────────────────────────────────────────────┘\e[39m

\e[1G\e[8A\e[J
\e[90m ┌\e[39m \e[36mPick a command\e[39m \e[90m──────────────────────────────────────────────┐\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mAbout\e[22m                                                    \e[90m│\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mLogs\e[22m                                                     \e[90m│\e[39m
\e[90m │\e[39m \e[36m›\e[39m ◻ Vite                                                     \e[90m│\e[39m
\e[90m │\e[39m   \e[2m◻\e[22m \e[2mReverb\e[22m                                                   \e[90m│\e[39m
\e[90m └──────────────────────────────────────────────────────────────┘\e[39m
TXT;

        $this->assertTerminalMatch($output);
    }

    #[Test]
    public function single_zero_doesnt_work_wtf()
    {
        $this->assertTerminalMatch('0');
    }

    #[Test]
    public function the_daniel_weaver_test_bless_you_daniel()
    {
        $line = str_repeat('a', 128);

        $this->assertTerminalMatch($line . '0');
    }
}
