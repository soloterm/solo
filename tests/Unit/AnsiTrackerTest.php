<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use Illuminate\Support\Benchmark;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Support\AnsiTracker;

class AnsiTrackerTest extends Base
{
    #[Test]
    public function add_foreground_colors(): void
    {
        $ansi = new AnsiTracker;

        // Black
        $ansi->addAnsiCode(30);

        $this->assertEquals("\e[30m", $ansi->getActiveAsAnsi());

        // Red
        $ansi->addAnsiCode(31);

        $this->assertEquals("\e[31m", $ansi->getActiveAsAnsi());
    }

    #[Test]
    public function default_foreground(): void
    {
        $ansi = new AnsiTracker;

        // FG
        $ansi->addAnsiCode(30);
        // BG
        $ansi->addAnsiCode(40);
        // Reset FG only
        $ansi->addAnsiCode(39);

        $this->assertEquals("\e[39;40m", $ansi->getActiveAsAnsi());
    }

    #[Test]
    public function default_background(): void
    {
        $ansi = new AnsiTracker;

        // FG
        $ansi->addAnsiCode(30);
        // BG
        $ansi->addAnsiCode(40);
        // Reset BG only
        $ansi->addAnsiCode(49);

        $this->assertEquals("\e[30;49m", $ansi->getActiveAsAnsi());
    }

    #[Test]
    public function reset_decorations(): void
    {
        $ansi = new AnsiTracker;

        // Bold
        $ansi->addAnsiCode(1);
        $this->assertEquals("\e[1m", $ansi->getActiveAsAnsi());

        // unset bold
        $ansi->addAnsiCode(22);
        $this->assertEquals("\e[22m", $ansi->getActiveAsAnsi());

        // Bold
        $ansi->addAnsiCode(1);
        // Italic
        $ansi->addAnsiCode(3);
        $this->assertEquals("\e[1;3m", $ansi->getActiveAsAnsi());

        // unset bold
        $ansi->addAnsiCode(22);
        $this->assertEquals("\e[3;22m", $ansi->getActiveAsAnsi());
    }

    #[Test]
    public function reset_bold_and_dim(): void
    {
        $ansi = new AnsiTracker;

        // Bold
        $ansi->addAnsiCode(1);
        $ansi->addAnsiCode(2);
        $this->assertEquals("\e[1;2m", $ansi->getActiveAsAnsi());

        // unset both
        $ansi->addAnsiCode(22);
        $this->assertEquals("\e[22m", $ansi->getActiveAsAnsi());
    }

    #[Test]
    public function reset_all(): void
    {
        $ansi = new AnsiTracker;

        // Black fg
        $ansi->addAnsiCode(30);
        // Blue bg
        $ansi->addAnsiCode(44);
        // Italic
        $ansi->addAnsiCode(3);

        $this->assertEquals("\e[3;30;44m", $ansi->getActiveAsAnsi());

        $ansi->addAnsiCode(0);

        $this->assertEquals("\e[0m", $ansi->getActiveAsAnsi());
    }

    #[Test]
    public function clear_test(): void
    {
        $ansi = new AnsiTracker;
        $ansi->buffer->buffer = [
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
        ];

        $ansi->buffer->clear(
            startRow: 1,
            startCol: 3,
            endRow: 3,
            endCol: 2
        );

        $buffer = array_map(fn($line) => implode(',', $line), $ansi->buffer->buffer);

        $this->assertSame([
            '1,1,1,1,1',
            '1,1,1',
            '',
            '0,0,0,1,1',
            '1,1,1,1,1',
        ], $buffer);
    }

    #[Test]
    public function clear_beyond_cols_test(): void
    {
        $ansi = new AnsiTracker;
        $ansi->buffer->buffer = [
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
        ];

        $ansi->buffer->clear(startRow: 1, startCol: -3, endRow: 3, endCol: 50);

        $buffer = array_map(fn($line) => implode(',', $line), $ansi->buffer->buffer);

        $this->assertSame([
            '1,1,1,1,1',
            '',
            '',
            '',
            '1,1,1,1,1',
        ], $buffer);
    }

    #[Test]
    public function clear_beyond_rows_test(): void
    {
        $ansi = new AnsiTracker;
        $ansi->buffer->buffer = [
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
        ];

        $ansi->buffer->clear(startRow: 10, startCol: 1, endRow: 13, endCol: 10);

        $buffer = array_map(fn($line) => implode(',', $line), $ansi->buffer->buffer);

        $this->assertSame([
            '1,1,1,1,1',
            '1,1,1,1,1',
            '1,1,1,1,1',
            '1,1,1,1,1',
            '1,1,1,1,1',
        ], $buffer);
    }

    #[Test]
    public function same_line_clear(): void
    {
        $ansi = new AnsiTracker;
        $ansi->buffer->buffer = [
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
            [1, 1, 1, 1, 1],
        ];

        $ansi->buffer->clear(startRow: 1, startCol: 1, endRow: 1, endCol: 2);

        $buffer = array_map(fn($line) => implode(',', $line), $ansi->buffer->buffer);

        $this->assertSame([
            '1,1,1,1,1',
            '1,0,0,1,1',
            '1,1,1,1,1',
            '1,1,1,1,1',
            '1,1,1,1,1',
        ], $buffer);
    }

    //    #[Test]
    //    public function speed(): void
    //    {
    //        $ansi = new AnsiBuffer;
    //
    //        $mask = (1 << 0) + (1 << 3);
    //
    //        Benchmark::dd(function () use ($ansi, $mask) {
    //            $ansi->ansiCodesFromMask($mask);
    //        }, 20000);
    //    }

}
