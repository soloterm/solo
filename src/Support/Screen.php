<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Closure;
use Exception;
use Illuminate\Support\HigherOrderCollectionProxy;
use SoloTerm\Solo\Buffers\AnsiBuffer;
use SoloTerm\Solo\Buffers\Buffer;
use SoloTerm\Solo\Buffers\PrintableBuffer;

class Screen
{
    public AnsiBuffer $ansi;

    public PrintableBuffer $printable;

    /**
     * A higher-order collection of both the Screen and ANSI buffers
     * so we can call methods on both of them at once. The type-
     * hint doesn't match the actual property type on purpose.
     *
     * @var Buffer
     *
     * @noinspection PhpDocFieldTypeMismatchInspection
     */
    public HigherOrderCollectionProxy $buffers;

    public int $cursorRow = 0;

    public int $cursorCol = 0;

    public int $linesOffScreen = 0;

    public int $width;

    public int $height;

    protected ?Closure $respondVia = null;

    protected array $stashedCursor = [];

    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;

        $this->ansi = new AnsiBuffer;
        $this->printable = (new PrintableBuffer)->setWidth($width);

        $this->buffers = collect([$this->ansi, $this->printable])->each;
    }

    public function respondToQueriesVia(Closure $closure): static
    {
        $this->respondVia = $closure;

        return $this;
    }

    public function output(): string
    {
        $ansi = $this->ansi->compressedAnsiBuffer();
        $printable = $this->printable->getBuffer();
        $outputLines = [];

        foreach ($printable as $lineIndex => $line) {
            // Get ANSI codes for this line (if any).
            $ansiForLine = $ansi[$lineIndex] ?? [];
            $lineStr = '';

            for ($col = 0; $col < count($line); $col++) {
                $lineStr .= ($ansiForLine[$col] ?? '') . $line[$col];
            }

            $outputLines[] = $lineStr;
        }

        return implode(PHP_EOL, $outputLines);
    }

    public function write(string $content): static
    {
        // Backspace character gets replaced with "move one column backwards."
        // Carriage returns get replaced with a code to move to column 0.

        $content = str_replace(
            search: ["\x08", "\r"],
            replace: ["\e[D", "\e[G"],
            subject: $content
        );

        // Split the line by ANSI codes. Each item in the resulting array
        // will be a set of printable characters or an ANSI code.
        $parts = AnsiMatcher::split($content);

        foreach ($parts as $part) {
            if ($part instanceof AnsiMatch) {
                if ($part->command) {
                    $this->handleAnsiCode($part);
                }
            } else {
                if ($part === '') {
                    continue;
                }

                $lines = explode(PHP_EOL, $part);
                $linesCount = count($lines);

                foreach ($lines as $index => $line) {
                    $this->handlePrintableCharacters($line);

                    if ($index < $linesCount - 1) {
                        $this->newlineWithScroll();
                    }
                }
            }
        }

        return $this;
    }

    public function writeln(string $content): void
    {
        if ($this->cursorCol === 0) {
            $this->write("$content\n");
        } else {
            $this->write("\n$content\n");
        }
    }

    protected function handleAnsiCode(AnsiMatch $ansi)
    {
        $command = $ansi->command;
        $param = $ansi->params;

        // Some commands have a default of zero and some have a default of one. Just
        // make both options and decide within the body of the if statement.
        // We could do a match here but it doesn't seem worth it.
        $paramDefaultZero = ($param !== '' && is_numeric($param)) ? intval($param) : 0;
        $paramDefaultOne = ($param !== '' && is_numeric($param)) ? intval($param) : 1;

        if ($command === 'A') {
            // Cursor up
            $this->moveCursorRow(relative: -$paramDefaultOne);

        } elseif ($command === 'B') {
            // Cursor down
            $this->moveCursorRow(relative: $paramDefaultOne);

        } elseif ($command === 'C') {
            // Cursor forward
            $this->moveCursorCol(relative: $paramDefaultOne);

        } elseif ($command === 'D') {
            // Cursor backward
            $this->moveCursorCol(relative: -$paramDefaultOne);

        } elseif ($command === 'E') {
            // Cursor to beginning of line, a number of lines down
            $this->moveCursorRow(relative: $paramDefaultOne);
            $this->moveCursorCol(absolute: 0);

        } elseif ($command === 'F') {
            // Cursor to beginning of line, a number of lines up
            $this->moveCursorRow(relative: -$paramDefaultOne);
            $this->moveCursorCol(absolute: 0);

        } elseif ($command === 'G') {
            // Cursor to column #, accounting for one-based indexing.
            $this->moveCursorCol($paramDefaultOne - 1);

        } elseif ($command === 'H') {
            $this->handleAbsoluteMove($ansi->params);

        } elseif ($command === 'I') {
            $this->handleTabulationMove($paramDefaultOne);

        } elseif ($command === 'J') {
            $this->handleEraseDisplay($paramDefaultZero);

        } elseif ($command === 'K') {
            $this->handleEraseInLine($paramDefaultZero);

        } elseif ($command === 'L') {
            $this->handleInsertLines($paramDefaultOne);

        } elseif ($command === 'S') {
            $this->handleScrollUp($paramDefaultOne);

        } elseif ($command === 'T') {
            $this->handleScrollDown($paramDefaultOne);

        } elseif ($command === 'l' || $command === 'h') {
            // Show/hide cursor. We simply ignore these.

        } elseif ($command === 'm') {
            // Colors / graphics mode
            $this->handleSGR($param);

        } elseif ($command === '7') {
            $this->saveCursor();

        } elseif ($command === '8') {
            $this->restoreCursor();

        } elseif ($param === '?' && in_array($command, ['10', '11'])) {
            // Ask for the foreground or background color.
            $this->handleQueryCode($command, $param);

        } elseif ($command === 'n' && $param === '6') {
            // Ask for the cursor position.
            $this->handleQueryCode($command, $param);
        }

        // @TODO Unhandled ansi command. Throw an error? Log it?
    }

    protected function newlineWithScroll()
    {
        if (($this->cursorRow - $this->linesOffScreen) >= $this->height - 1) {
            $this->linesOffScreen++;
        }

        $this->moveCursorRow(relative: 1);
        $this->moveCursorCol(absolute: 0);
    }

    protected function handlePrintableCharacters(string $text): void
    {
        if ($text === '') {
            return;
        }

        $this->printable->expand($this->cursorRow);

        [$advance, $remainder] = $this->printable->writeString($this->cursorRow, $this->cursorCol, $text);

        $this->ansi->fillBufferWithActiveFlags($this->cursorRow, $this->cursorCol, $this->cursorCol + $advance - 1);

        $this->cursorCol += $advance;

        // If there's overflow (i.e. text that didn't fit on this line),
        // move to a new line and recursively handle it.
        if ($remainder !== '') {
            $this->newlineWithScroll();
            $this->handlePrintableCharacters($remainder);
        }
    }

    public function saveCursor()
    {
        $this->stashedCursor = [
            $this->cursorCol,
            $this->cursorRow - $this->linesOffScreen
        ];
    }

    public function restoreCursor()
    {
        if ($this->stashedCursor) {
            [$col, $row] = $this->stashedCursor;
            $this->moveCursorCol(absolute: $col);
            $this->moveCursorRow(absolute: $row);
            $this->stashedCursor = [];
        }
    }

    public function moveCursorCol(?int $absolute = null, ?int $relative = null)
    {
        $this->ensureCursorParams($absolute, $relative);

        // Inside this method, position is zero-based.

        $max = $this->width;
        $min = 0;

        $position = $this->cursorCol;

        if (!is_null($absolute)) {
            $position = $absolute;
        }

        if (!is_null($relative)) {
            // Relative movements cannot put the cursor at the very end, only absolute
            // movements can. Not sure why, but I verified the behavior manually.
            $max -= 1;
            $position += $relative;
        }

        $position = min($position, $max);
        $position = max($min, $position);

        $this->cursorCol = $position;
    }

    public function moveCursorRow(?int $absolute = null, ?int $relative = null)
    {
        $this->ensureCursorParams($absolute, $relative);

        $max = $this->height + $this->linesOffScreen - 1;
        $min = $this->linesOffScreen;

        $position = $this->cursorRow;

        if (!is_null($absolute)) {
            $position = $absolute + $this->linesOffScreen;
        }

        if (!is_null($relative)) {
            $position += $relative;
        }

        $position = min($position, $max);
        $position = max($min, $position);

        $this->cursorRow = $position;

        $this->printable->expand($this->cursorRow);
    }

    protected function moveCursor(string $direction, ?int $absolute = null, ?int $relative = null): void
    {
        $this->ensureCursorParams($absolute, $relative);

        $property = $direction === 'x' ? 'cursorCol' : 'cursorRow';
        $max = $direction === 'x' ? $this->width : ($this->height + $this->linesOffScreen);
        $min = $direction === 'x' ? 0 : $this->linesOffScreen;

        if (!is_null($absolute)) {
            $this->{$property} = $absolute;
        }

        if (!is_null($relative)) {
            $this->{$property} += $relative;
        }

        $this->{$property} = min(
            max($this->{$property}, $min),
            $max - 1
        );
    }

    protected function ensureCursorParams($absolute, $relative): void
    {
        if (!is_null($absolute) && !is_null($relative)) {
            throw new Exception('Use either relative or absolute, but not both.');
        }

        if (is_null($absolute) && is_null($relative)) {
            throw new Exception('Relative and absolute cannot both be blank.');
        }
    }

    /**
     * Handle SGR (Select Graphic Rendition) ANSI codes for colors and styles.
     */
    protected function handleSGR(string $params): void
    {
        // Support multiple codes, like \e[30;41m
        $codes = array_map(intval(...), explode(';', $params));

        $this->ansi->addAnsiCodes(...$codes);
    }

    protected function handleTabulationMove(int $tabs)
    {
        $tabStop = 8;

        // If current column isn't at a tab stop, move to the next one.
        $remainder = $this->cursorCol % $tabStop;
        if ($remainder !== 0) {
            $this->cursorCol += ($tabStop - $remainder);
            $tabs--; // one tab stop consumed
        }

        // For any remaining tabs, move by full tab stops.
        if ($tabs > 0) {
            $this->cursorCol += $tabs * $tabStop;
        }
    }

    protected function handleAbsoluteMove(string $params)
    {
        if ($params !== '') {
            [$row, $col] = explode(';', $params);
            $row = $row === '' ? 1 : intval($row);
            $col = $col === '' ? 1 : intval($col);
        } else {
            $row = 1;
            $col = 1;
        }

        // ANSI codes are 1-based, while our system is 0-based.
        $this->moveCursorRow(absolute: --$row);
        $this->moveCursorCol(absolute: --$col);
    }

    protected function handleEraseDisplay(int $param): void
    {
        if ($param === 0) {
            // \e[0J - Erase from cursor until end of screen
            $this->buffers->clear(
                startRow: $this->cursorRow,
                startCol: $this->cursorCol
            );
        } elseif ($param === 1) {
            // \e[1J - Erase from cursor until beginning of screen
            $this->buffers->clear(
                startRow: $this->linesOffScreen,
                endRow: $this->cursorRow,
                endCol: $this->cursorCol
            );
        } elseif ($param === 2) {
            // \e[2J - Erase entire screen
            $this->buffers->clear(
                startRow: $this->linesOffScreen,
                endRow: $this->linesOffScreen + $this->height,
            );
        }
    }

    protected function handleInsertLines(int $lines): void
    {
        $allowed = $this->height - ($this->cursorRow - $this->linesOffScreen);
        $afterCursor = $lines + count($this->printable->buffer) - $this->cursorRow;

        $chop = $afterCursor - $allowed;

        // Ensure the buffer has enough rows so that $this->cursorRow is defined.
        if (!isset($this->printable->buffer[$this->cursorRow])) {
            $this->printable->expand($this->cursorRow);
        }

        if (!isset($this->ansi->buffer[$this->cursorRow])) {
            $this->ansi->expand($this->cursorRow);
        }

        // Create an array of $lines empty arrays.
        $newLines = array_fill(0, $lines, []);

        // Insert the new lines at the cursor row index.
        // array_splice will insert these new arrays and push the existing rows down.
        array_splice($this->printable->buffer, $this->cursorRow, 0, $newLines);
        array_splice($this->ansi->buffer, $this->cursorRow, 0, $newLines);

        if ($chop > 0) {
            array_splice($this->printable->buffer, -$chop);
            array_splice($this->ansi->buffer, -$chop);
        }
    }

    protected function handleScrollDown(int $param): void
    {
        $stash = $this->cursorRow;

        $this->cursorRow = $this->linesOffScreen;

        $this->handleInsertLines($param);

        $this->cursorRow = $stash;
    }

    protected function handleScrollUp(int $param): void
    {
        $stash = $this->cursorRow;

        $this->printable->expand($this->height);

        $this->cursorRow = count($this->printable->buffer) + $param - 1;

        $this->handleInsertLines($param);

        $this->linesOffScreen += $param;

        $this->cursorRow = $stash + $param;
    }

    protected function handleEraseInLine(int $param): void
    {
        if ($param === 0) {
            // \e[0K - Erase from cursor to end of line
            $this->buffers->clear(
                startRow: $this->cursorRow,
                startCol: $this->cursorCol,
                endRow: $this->cursorRow
            );

            $background = $this->ansi->getActiveBackground();

            if ($background !== 0) {
                $this->printable->fill(' ', $this->cursorRow, $this->cursorCol, $this->width - 1);
                $this->ansi->fill($background, $this->cursorRow, $this->cursorCol, $this->width - 1);
            }
        } elseif ($param == 1) {
            // \e[1K - Erase start of line to the cursor
            $this->buffers->clear(
                startRow: $this->cursorRow,
                endRow: $this->cursorRow,
                endCol: $this->cursorCol
            );
        } elseif ($param === 2) {
            // \e[2K - Erase the entire line
            $this->buffers->clear(
                startRow: $this->cursorRow,
                endRow: $this->cursorRow
            );
        }
    }

    protected function handleQueryCode(string $command, string $param): void
    {
        if (!is_callable($this->respondVia)) {
            return;
        }

        $response = match ($param . $command) {
            // Foreground color
            // @TODO not hardcode this, somehow
            '?10' => "\e]10;rgb:0000/0000/0000 \e \\",
            // Background
            '?11' => "\e]11;rgb:FFFF/FFFF/FFFF \e \\",
            // Cursor
            '6n' => "\e[" . ($this->cursorRow + 1) . ';' . ($this->cursorCol + 1) . 'R',
            default => null,
        };

        if ($response) {
            call_user_func($this->respondVia, $response);
        }
    }
}
