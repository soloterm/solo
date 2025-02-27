<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Support\AnsiMatcher;

class AnsiMatchTest extends Base
{
    //    #[Test]
    //    public function michell_hashimoto()
    //    {
    //        // https://x.com/mitchellh/status/1878915045524124097
    //        $sequence ="[4:3;38;2;175;175;215;58:2::190:80:70m";
    //        $result = AnsiMatcher::split("\e{$sequence}test");
    //
    //        dd($result);
    //    }

    #[Test]
    public function all(): void
    {
        // https://raw.githubusercontent.com/chalk/ansi-regex/refs/heads/main/fixtures/ansi-codes.js
        $fixtures = [
            // From http://www.umich.edu/~archive/apple2/misc/programmers/vt100.codes.txt
            ['A', ['Cursor up']],
            ['B', ['Cursor down']],
            ['C', ['Cursor right']],
            ['D', ['Cursor left']],
            ['H', ['Cursor to home']],
            ['I', ['Reverse line feed']],
            ['J', ['Erase to end of screen']],
            ['K', ['Erase to end of line']],
            ['S', ['Scroll up']],
            ['T', ['Scroll down']],
            ['Z', ['Identify']],
            ['=', ['Enter alternate keypad mode']],
            ['>', ['Exit alternate keypad mode']],
            ['1', ['Graphics processor on']],
            ['2', ['Graphics processor off']],
            ['<', ['Enter ANSI mode']],
            ['s', ['Cursor save']],
            ['u', ['Cursor restore']],

            // From https://espterm.github.io/docs/VT100%20escape%20codes.html
            ['[176A', ['Cursor up Pn lines']],
            ['[176B', ['Cursor down Pn lines']],
            ['[176C', ['Cursor forward Pn characters (right)']],
            ['[176D', ['Cursor backward Pn characters (left)']],
            ['[176;176H', ['Direct cursor addressing, where Pl is line#, Pc is column#']],
            ['[176;176f', ['Direct cursor addressing, where Pl is line#, Pc is column#']],

            ['7', ['Save cursor and attributes']],
            ['8', ['Restore cursor and attributes']],

            ['#3', ['Change this line to double-height top half']],
            ['#4', ['Change this line to double-height bottom half']],
            ['#5', ['Change this line to single-width single-height']],
            ['#6', ['Change this line to double-width single-height']],

            ['[176;176;176;176;176;176;176m', ['Text Styles']],
            ['[176;176;176;176;176;176;176q', ['Programmable LEDs']],

            ['[K', ['Erase from cursor to end of line']],
            ['[0K', ['Same']],
            ['[1K', ['Erase from beginning of line to cursor']],
            ['[2K', ['Erase line containing cursor']],
            ['[J', ['Erase from cursor to end of screen']],
            ['[0J', ['Same']],
            ['[2J', ['Erase entire screen']],
            ['[P', ['Delete character']],
            ['[0P', ['Delete character (0P)']],
            ['[2P', ['Delete 2 characters']],

            ['(A', ['United Kingdom (UK) (Character Set G0)']],
            [')A', ['United Kingdom (UK) (Character Set G1)']],
            ['(B', ['United States (USASCII) (Character Set G0)']],
            [')B', ['United States (USASCII) (Character Set G1)']],
            ['(0', ['Special graphics/line drawing set (Character Set G0)']],
            [')0', ['Special graphics/line drawing set (Character Set G1)']],
            ['(1', ['Alternative character ROM (Character Set G0)']],
            [')1', ['Alternative character ROM (Character Set G1)']],
            ['(2', ['Alternative graphic ROM (Character Set G0)']],
            [')2', ['Alternative graphic ROM (Character Set G1)']],

            ['H', ['Set tab at current column']],
            ['[g', ['Clear tab at current column']],
            ['[0g', ['Same']],
            ['[3g', ['Clear all tabs']],

            ['[6n', ['Cursor position report']],
            ['[176;176R', ['(response; Pl=line#; Pc=column#)']],
            ['[5n', ['Status report']],
            ['[c', ['(response; terminal Ok)']],
            ['[0c', ['(response; teminal not Ok)']],
            ['[?1;176c', ['response; where Ps is option present:']],

            ['c', ['Causes power-up reset routine to be executed']],
            ['#8', ['Fill screen with "E"']],
            ['[2;176y',
                ['Invoke Test(s), where Ps is a decimal computed by adding the numbers of the desired tests to be executed']],

            // From http://ascii-table.com/ansi-escape-sequences-vt-100.php
            ['[176A', ['Move cursor up n lines', 'CUU']],
            ['[176B', ['Move cursor down n lines', 'CUD']],
            ['[176C', ['Move cursor right n lines', 'CUF']],
            ['[176D', ['Move cursor left n lines', 'CUB']],
            ['[176;176H', ['Move cursor to screen location v,h', 'CUP']],
            ['[176;176f', ['Move cursor to screen location v,h', 'CUP']],
            ['[176;176r', ['Set top and bottom lines of a window', 'DECSTBM']],
            ['[176;176R', ['Response: cursor is at v,h', 'CPR']],

            ['[?1;1760c', ['Response: terminal type code n', 'DA']],

            ['[20h', ['Set new line mode', 'LMN']],
            ['[?1h', ['Set cursor key to application', 'DECCKM']],
            ['[?3h', ['Set number of columns to 132', 'DECCOLM']],
            ['[?4h', ['Set smooth scrolling', 'DECSCLM']],
            ['[?5h', ['Set reverse video on screen', 'DECSCNM']],
            ['[?6h', ['Set origin to relative', 'DECOM']],
            ['[?7h', ['Set auto-wrap mode', 'DECAWM']],
            ['[?8h', ['Set auto-repeat mode', 'DECARM']],
            ['[?9h', ['Set interlacing mode', 'DECINLM']],
            ['[20l', ['Set line feed mode', 'LMN']],
            ['[?1l', ['Set cursor key to cursor', 'DECCKM']],
            ['[?2l', ['Set VT52 (versus ANSI)', 'DECANM']],
            ['[?3l', ['Set number of columns to 80', 'DECCOLM']],
            ['[?4l', ['Set jump scrolling', 'DECSCLM']],
            ['[?5l', ['Set normal video on screen', 'DECSCNM']],
            ['[?6l', ['Set origin to absolute', 'DECOM']],
            ['[?7l', ['Reset auto-wrap mode', 'DECAWM']],
            ['[?8l', ['Reset auto-repeat mode', 'DECARM']],
            ['[?9l', ['Reset interlacing mode', 'DECINLM']],

            ['N', ['Set single shift 2', 'SS2']],
            ['O', ['Set single shift 3', 'SS3']],

            ['[m', ['Turn off character attributes', 'SGR0']],
            ['[0m', ['Turn off character attributes', 'SGR0']],
            ['[1m', ['Turn bold mode on', 'SGR1']],
            ['[2m', ['Turn low intensity mode on', 'SGR2']],
            ['[4m', ['Turn underline mode on', 'SGR4']],
            ['[5m', ['Turn blinking mode on', 'SGR5']],
            ['[7m', ['Turn reverse video on', 'SGR7']],
            ['[8m', ['Turn invisible text mode on', 'SGR8']],

            ['[9m', ['strikethrough on', '--']],
            ['[22m', ['bold off (see below)', '--']],
            ['[23m', ['italics off', '--']],
            ['[24m', ['underline off', '--']],
            ['[27m', ['inverse off', '--']],
            ['[29m', ['strikethrough off', '--']],
            ['[30m', ['set foreground color to black', '--']],
            ['[31m', ['set foreground color to red', '--']],
            ['[32m', ['set foreground color to green', '--']],
            ['[33m', ['set foreground color to yellow', '--']],
            ['[34m', ['set foreground color to blue', '--']],
            ['[35m', ['set foreground color to magenta (purple)', '--']],
            ['[36m', ['set foreground color to cyan', '--']],
            ['[37m', ['set foreground color to white', '--']],
            ['[39m', ['set foreground color to default (white)', '--']],
            ['[40m', ['set background color to black', '--']],
            ['[41m', ['set background color to red', '--']],
            ['[42m', ['set background color to green', '--']],
            ['[43m', ['set background color to yellow', '--']],
            ['[44m', ['set background color to blue', '--']],
            ['[45m', ['set background color to magenta (purple)', '--']],
            ['[46m', ['set background color to cyan', '--']],
            ['[47m', ['set background color to white', '--']],
            ['[49m', ['set background color to default (black)', '--']],

            ['[H', ['Move cursor to upper left corner', 'cursorhome']],
            ['[;H', ['Move cursor to upper left corner', 'cursorhome']],
            ['[f', ['Move cursor to upper left corner', 'hvhome']],
            ['[;f', ['Move cursor to upper left corner', 'hvhome']],
            ['M', ['Move/scroll window down one line', 'RI']],
            ['E', ['Move to next line', 'NEL']],

            ['H', ['Set a tab at the current column', 'HTS']],
            ['[g', ['Clear a tab at the current column', 'TBC']],
            ['[0g', ['Clear a tab at the current column', 'TBC']],
            ['[3g', ['Clear all tabs', 'TBC']],

            ['[K', ['Clear line from cursor right', 'EL0']],
            ['[0K', ['Clear line from cursor right', 'EL0']],
            ['[1K', ['Clear line from cursor left', 'EL1']],
            ['[2K', ['Clear entire line', 'EL2']],
            ['[J', ['Clear screen from cursor down', 'ED0']],
            ['[0J', ['Clear screen from cursor down', 'ED0']],
            ['[1J', ['Clear screen from cursor up', 'ED1']],
            ['[2J', ['Clear entire screen', 'ED2']],

            ['[c', ['Identify what terminal type', 'DA']],
            ['[0c', ['Identify what terminal type (another)', 'DA']],
            ['c', ['Reset terminal to initial state', 'RIS']],
            ['[2;1y', ['Confidence power up test', 'DECTST']],
            ['[2;2y', ['Confidence loopback test', 'DECTST']],
            ['[2;9y', ['Repeat power up test', 'DECTST']],
            ['[2;10y', ['Repeat loopback test', 'DECTST']],
            ['[0q', ['Turn off all four leds', 'DECLL0']],
            ['[1q', ['Turn on LED #1', 'DECLL1']],
            ['[2q', ['Turn on LED #2', 'DECLL2']],
            ['[3q', ['Turn on LED #3', 'DECLL3']],
            ['[4q', ['Turn on LED #4', 'DECLL4']],

            // From http://ascii-table.com/ansi-escape-sequences-vt-100.php
            ['7', ['Save cursor position and attributes', 'DECSC']],
            ['8', ['Restore cursor position and attributes', 'DECSC']],

            ['=', ['Set alternate keypad mode', 'DECKPAM']],
            ['>', ['Set numeric keypad mode', 'DECKPNM']],

            ['(A', ['Set United Kingdom G0 character set', 'setukg0']],
            [')A', ['Set United Kingdom G1 character set', 'setukg1']],
            ['(B', ['Set United States G0 character set', 'setusg0']],
            [')B', ['Set United States G1 character set', 'setusg1']],
            ['(0', ['Set G0 special chars. & line set', 'setspecg0']],
            [')0', ['Set G1 special chars. & line set', 'setspecg1']],
            ['(1', ['Set G0 alternate character ROM', 'setaltg0']],
            [')1', ['Set G1 alternate character ROM', 'setaltg1']],
            ['(2', ['Set G0 alt char ROM and spec. graphics', 'setaltspecg0']],
            [')2', ['Set G1 alt char ROM and spec. graphics', 'setaltspecg1']],

            ['#3', ['Double-height letters, top half', 'DECDHL']],
            ['#4', ['Double-height letters, bottom half', 'DECDHL']],
            ['#5', ['Single width, single height letters', 'DECSWL']],
            ['#6', ['Double width, single height letters', 'DECDWL']],
            ['#8', ['Screen alignment display', 'DECALN']],

            ['5n', ['Device status report', 'DSR']],
            ['0n', ['Response: terminal is OK', 'DSR']],
            ['3n', ['Response: terminal is not OK', 'DSR']],
            ['6n', ['Get cursor position', 'DSR']],

            // `urxvt` escapes
            ['[5~', ['URxvt.keysym.Prior']],
            ['[6~', ['URxvt.keysym.Next']],
            ['[7~', ['URxvt.keysym.Home']],
            ['[8~', ['URxvt.keysym.End']],
            ['[A', ['URxvt.keysym.Up']],
            ['[B', ['URxvt.keysym.Down']],
            ['[C', ['URxvt.keysym.Right']],
            ['[D', ['URxvt.keysym.Left']],
            ['[3;5;5t', ['URxvt.keysym.C-M-q']],
            ['[3;5;606t', ['URxvt.keysym.C-M-y']],
            ['[3;1605;5t', ['URxvt.keysym.C-M-e']],
            ['[3;1605;606t', ['URxvt.keysym.C-M-c']],
            [']710;9x15bold', ['URxvt.keysym.font']],
        ];

        foreach ($fixtures as $fixture) {
            $sequence = $fixture[0];
            $description = $fixture[1][0];

            $result = AnsiMatcher::split("\e{$sequence}test");

            // echo "Testing $sequence ($description) \n";

            $this->assertEquals("\e{$sequence}", Arr::get($result, 0));
            $this->assertEquals('test', Arr::get($result, 1));
        }
    }
}
