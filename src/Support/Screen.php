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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HigherOrderCollectionProxy;

class Screen
{
    public AnsiTracker $ansi;

    public Buffer $buffer;

    /**
     * A higher-order collection of both the Screen and ANSI buffers
     * so we can call methods on both of them at once. The type-
     * hint doesn't match the actual property type on purpose.
     *
     * @var Buffer
     *
     * @noinspection PhpDocFieldTypeMismatchInspection
     */
    public HigherOrderCollectionProxy $bothBuffers;

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
        $this->ansi = new AnsiTracker;
        $this->buffer = new Buffer(usesStrings: true);

        $this->bothBuffers = collect([$this->ansi->buffer, $this->buffer])->each;
    }

    public function respondToQueriesVia(Closure $closure): static
    {
        $this->respondVia = $closure;

        return $this;
    }

    public function output(): string
    {
        // Get the most minimal representation of the ANSI
        // buffer possible, eliminating all duplicates.
        $ansi = $this->ansi->compressedAnsiBuffer();

        $buffer = $this->buffer->getBuffer();

        foreach ($buffer as $k => &$line) {
            // At this point, the keys represent the column where the ANSI code should
            // be placed in the string and the values are the ANSI strings.
            $ansiForLine = $ansi[$k] ?? [];

            // Sort them in reverse by position so that we can start at the end of the
            // string and work backwards so that all positions remain valid.
            krsort($ansiForLine);

            // Now, work backwards through the line inserting the codes.
            foreach ($ansiForLine as $pos => $code) {
                $line = mb_substr($line, 0, $pos, 'UTF-8') . $code . mb_substr($line, $pos, null, 'UTF-8');
            }
        }

        return implode(PHP_EOL, $buffer);
    }

    public function write(string $content): static
    {
        // Carriage returns get replaced with a code to move to column 0.
        $content = str_replace("\r", "\e[G", $content);

        // Split the line by ANSI codes. Each item in the resulting array
        // will be a set of printable characters or an ANSI code.
        $parts = AnsiMatcher::split($content);

        $i = 0;

        while ($i < count($parts)) {
            $part = $parts[$i];

            if ($part instanceof AnsiMatch) {
                if ($part->command) {
                    $this->handleAnsiCode($part);
                } else {
                    // Log::error('Unknown ANSI match:', [
                    //     'line' => $content,
                    //     'part' => $part->raw,
                    // ]);
                }
            } else {
                $lines = explode(PHP_EOL, $part);

                foreach ($lines as $index => $line) {
                    $this->handlePrintableCharacters($line);

                    if ($index < count($lines) - 1) {
                        $this->newlineWithScroll();
                    }
                }
            }

            $i++;
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

        } elseif ($command === 'J') {
            $this->handleEraseDisplay($paramDefaultZero);

        } elseif ($command === 'K') {
            $this->handleEraseInLine($paramDefaultZero);

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
        if ($this->cursorRow >= $this->height - 1) {
            $this->linesOffScreen++;
        }

        $this->moveCursorRow(relative: 1);
        $this->moveCursorCol(absolute: 0);
    }

    /**
     * Inserts printable text at the current cursor position in the current buffer line.
     * The insertion respects display widths (using mb_strwidth) so that wide characters,
     * like emojis, correctly overwrite the appropriate number of columns.
     */
    protected function handlePrintableCharacters(string $text): void
    {
        if ($text === '') {
            return;
        }

        // Ensure the current row exists.
        $this->buffer->expand($this->cursorRow);
        $lineContent = $this->buffer[$this->cursorRow];

        // It's possible that an emoji is occupying columns 1&2, and the
        // cursor is set to col 2, which means we'd splice a grapheme.
        // Here we snap backwards to the nearest grapheme boundary.
        $snapped = $this->snapCursorColToGraphemeBoundary($lineContent, $this->cursorCol);

        // If we moved the cursor back, we need to write in spaces to bring
        // us back to where we were originally. This puts the cursor in
        // the right spot without leaving half an emoji behind.
        if ($this->cursorCol !== $snapped) {
            $text = str_repeat(' ', $this->cursorCol - $snapped) . $text;
            $this->cursorCol = $snapped;
        }

        // Pad the line if the cursor is beyond its current display width.
        $currentWidth = mb_strwidth($lineContent, 'UTF-8');
        if ($this->cursorCol > $currentWidth) {
            $lineContent .= str_repeat(' ', $this->cursorCol - $currentWidth);
        }

        // Get the portion of the line before the cursor.
        [$before,] = $this->substrByDisplayWidth($lineContent, $this->cursorCol);

        // Determine how many columns remain on this line.
        $spaceRemaining = $this->width - $this->cursorCol;

        // Get the portion of $text that fits in the remaining space and capture any overflow.
        [$text, $overflow] = $this->substrByDisplayWidth($text, $spaceRemaining);

        // Calculate the display width of the inserted text.
        $insertWidth = mb_strwidth($text, 'UTF-8');

//        if ($text === '🐛️' || $text === '❤️') {
//            $text = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $text);
//            dd([
//                '$text' => $text,
//                'strlen' => strlen($text),
//                'mb_strlen' => mb_strlen($text, 'UTF-8'),
//                'mb_strwidth' => mb_strwidth($text, 'UTF-8'),
//                'grapheme' => grapheme_strlen($text),
//            ]);
//        }

        // Now, we need to remove from the current line the columns that will be overwritten.
        // That is, we skip from the current cursor position to (cursor + insertWidth).
        $skipCol = $this->cursorCol + $insertWidth;

        // The "after" part is what remains of the line after that skip.
        [, $after] = $this->substrByDisplayWidth($lineContent, $skipCol);

        // Build the new line by concatenating the "before", inserted text, and "after".
        $newLine = $before . $text . $after;

        // If the new line exceeds the allowed width, split it.
        if (mb_strwidth($newLine, 'UTF-8') > $this->width) {
            [$lineThatFits, $lineOverflow] = $this->substrByDisplayWidth($newLine, $this->width);
            $newLine = $lineThatFits;
            // Append the overflow from the line (if any) to the text overflow.
            $overflow = $lineOverflow . $overflow;
        }

        // Update the buffer with the newly composed line.
        $this->buffer[$this->cursorRow] = $newLine;

        if ($text === '🐛️' || $text === '❤️') {
//            dd($this->cursorCol, $insertWidth);

            // I think what needs to happen is I think if the string length of a piece of text doesn't match the mb string length of a piece of text, then this row needs to become an array of characters. I don't think we can continue to just keep it as a string. And the array should hold one character per column, even if that character is a multi-byte character. In the instance of a character that spans two columns, the second column that it occupies should be either null or some sort of like a constant so we know that it is a continuation. Where possible, we should keep the rows as strings because that's going to be a lot more memory efficient.
        }

        // Move the cursor forward by the display width of the inserted text.
        $this->cursorCol = mb_strlen( $before . $text, 'UTF-8');

        // Update the ANSI buffer for active flags if needed.
        $this->ansi->fillBufferWithActiveFlags(
            $this->cursorRow,
            $this->cursorCol - $insertWidth,
            $this->cursorCol - 1
        );

        // If there's overflow (i.e. text that didn't fit on this line),
        // move to a new line and recursively handle it.
        if ($overflow !== '') {
            $this->newlineWithScroll();
            $this->handlePrintableCharacters($overflow);
        }
    }

    /**
     * Ensures the cursor never lands in the middle of a wide grapheme.
     * If $col is inside a multi-width grapheme, we "snap" to the start of that grapheme.
     */
    protected function snapCursorColToGraphemeBoundary(string $line, int $col): int
    {
        // If the line is empty or the column is at/before zero, no need to adjust.
        if ($line === '' || $col <= 0) {
            return max(0, $col);
        }

        // Break the line into grapheme clusters.
        if (preg_match_all('/\X/u', $line, $matches) === false) {
            // If regex fails, just return the requested col as a fallback.
            return $col;
        }

        $graphemes = $matches[0];
        $currentWidth = 0;

        foreach ($graphemes as $g) {
            $gWidth = mb_strwidth($g, 'UTF-8');
            $start = $currentWidth;         // inclusive
            $end = $currentWidth + $gWidth; // exclusive

            // If $col is in the "middle" of this grapheme's columns, snap to the start.
            if ($col > $start && $col < $end) {
                return $start;
            }

            $currentWidth = $end;
            // If we've already passed the requested col, we can stop checking.
            if ($currentWidth >= $col) {
                break;
            }
        }

        // If we never found a partial overlap, we can safely return the original col.
        return $col;
    }


    /**
     * Returns an array with two elements:
     *   [0] => The substring that fits within $maxWidth display columns.
     *   [1] => The remainder of the string.
     *
     * This function uses extended grapheme cluster matching (supported by Symfony's
     * intl-grapheme polyfill) to ensure that complex characters (emoji, combining marks, etc.)
     * are not split in half. It measures each cluster's display width using mb_strwidth.
     *
     * @param  string  $string  The input string.
     * @param  int  $maxWidth  The maximum display width (in columns) allowed.
     *
     * @return array [string $fit, string $remainder]
     */
    function substrByDisplayWidth(string $string, int $maxWidth): array
    {
        $width = 0;
        $fit = '';

        // Split the string into extended grapheme clusters.
        // Symfony's intl-grapheme polyfill ensures \X works correctly even in older PHP versions.
        if (preg_match_all('/\X/u', $string, $matches) === false) {
            // Fallback: if regex fails, simply return the first $maxWidth characters.
            return [mb_substr($string, 0, $maxWidth, 'UTF-8'), ''];
        }
        $graphemes = $matches[0];
        $i = 0;
        foreach ($graphemes as $g) {
            $gWidth = mb_strwidth($g, 'UTF-8');
            // If adding this grapheme would exceed the max width, stop.
            if ($width + $gWidth > $maxWidth) {
                break;
            }
            $fit .= $g;
            $width += $gWidth;
            $i++;
        }
        // The remainder is all the graphemes not included in the fit.
        $remainder = implode('', array_slice($graphemes, $i));
        return [$fit, $remainder];
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
            $this->moveCursorRow(absolute: $row + $this->linesOffScreen);
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
            $position = $absolute;
        }

        if (!is_null($relative)) {
            $position += $relative;
        }

        $position = min($position, $max);
        $position = max($min, $position);

        $this->cursorRow = $position;

        $this->buffer->expand($this->cursorRow);
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
            $this->bothBuffers->clear(
                startRow: $this->cursorRow,
                startCol: $this->cursorCol
            );
        } elseif ($param === 1) {
            // \e[1J - Erase from cursor until beginning of screen
            $this->bothBuffers->clear(
                startRow: $this->linesOffScreen,
                endRow: $this->cursorRow,
                endCol: $this->cursorCol
            );
        } elseif ($param === 2) {
            // \e[2J - Erase entire screen
            $this->bothBuffers->clear(
                startRow: $this->linesOffScreen,
                endRow: $this->linesOffScreen + $this->height,
            );
        }
    }

    protected function handleEraseInLine(int $param): void
    {
        if ($param === 0) {
            // \e[0K - Erase from cursor to end of line
            $this->bothBuffers->clear(
                startRow: $this->cursorRow,
                startCol: $this->cursorCol,
                endRow: $this->cursorRow
            );

        } elseif ($param == 1) {
            // \e[1K - Erase start of line to the cursor
            $this->bothBuffers->clear(
                startRow: $this->cursorRow,
                endRow: $this->cursorRow,
                endCol: $this->cursorCol
            );
        } elseif ($param === 2) {
            // \e[2K - Erase the entire line
            $this->bothBuffers->clear(
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
