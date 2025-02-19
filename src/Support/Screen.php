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
            // Cursor home
            $this->moveCursorRow(absolute: 0);
            $this->moveCursorCol(absolute: 0);

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

    protected function handlePrintableCharacters(string $text): void
    {
        if (empty($text)) {
            return;
        }

        $this->buffer->expand($this->cursorRow);

        $lineContent = $this->buffer[$this->cursorRow];

        // If cursorCol is beyond current line length, pad with spaces.
        $paddingRequired = $this->cursorCol - mb_strlen($lineContent, 'UTF-8');
        if ($paddingRequired > 0) {
            $lineContent .= str_repeat(' ', $paddingRequired);
        }

        $spaceRemaining = $this->width - $this->cursorCol;

        // Text that doesn't fit on this line. We'll recursively call
        // this function at the very end to add it to a new line.
        $remainder = mb_substr($text, $spaceRemaining, null, 'UTF-8');

        // The text that can fit on this line.
        $text = mb_substr($text, 0, $spaceRemaining, 'UTF-8');

        // The part of the line before the cursor.
        $before = mb_substr($lineContent, 0, $this->cursorCol, 'UTF-8');

        // The part of the line after the cursor *and* after our new content.
        // It's possible we overwrote some characters, which is correct,
        // but we might not have overwritten everything, so we
        // need to append any leftovers.
        $after = mb_substr($lineContent, $this->cursorCol + mb_strlen($text, 'UTF-8'), null, 'UTF-8');

        $this->buffer[$this->cursorRow] = $before . $text . $after;

        $startCol = $this->cursorCol;

        // Update the cursor position forward by the length of the text
        $this->moveCursorCol(absolute: mb_strlen($before . $text, 'UTF-8'));

        // Fill the ANSI buffer with currently active flags, based
        // on where the cursor started and where it ended.
        $this->ansi->fillBufferWithActiveFlags($this->cursorRow, $startCol, max($startCol, $this->cursorCol - 1));

        if (!empty($remainder)) {
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
