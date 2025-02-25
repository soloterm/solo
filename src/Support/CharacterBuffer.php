<?php

namespace SoloTerm\Solo\Support;

use Exception;
use SoloTerm\Grapheme\Grapheme;

class CharacterBuffer
{
    public array $buffer = [];

    public int $width;

    public function __construct(int $width)
    {
        $this->width = $width;
    }

    /**
     * Writes a string into the buffer at the specified row and starting column.
     * The string is split into "units" (either single characters or grapheme clusters),
     * and each unit is inserted into one or more cells based on its display width.
     * If a unit has width > 1, its first cell gets the unit, and the remaining cells are set to PHP null.
     *
     * If the text overflows the available width on that row, the function stops writing and returns
     * an array containing the number of columns advanced and a string of the remaining characters.
     *
     * @param  int  $row  Row index (0-based).
     * @param  int  $col  Starting column index (0-based).
     * @param  string  $text  The text to write.
     * @return array [$advanceCursor, $remainder]
     *
     * @throws Exception if splitting into graphemes fails.
     */
    public function writeString(int $row, int $col, string $text): array
    {
        // Determine the units to iterate over: if the text is ASCII-only, we can split by character,
        // otherwise we split into grapheme clusters.
        if (strlen($text) === mb_strlen($text)) {
            $units = str_split($text);
        } else {
            if (preg_match_all('/\X/u', $text, $matches) === false) {
                throw new Exception('Error splitting text into grapheme clusters.');
            }

            $units = $matches[0];
        }

        $currentCol = $col;
        $advanceCursor = 0;
        $totalUnits = count($units);

        // Ensure that the row is not sparse.
        // If the row already exists, fill any missing indices before the starting column with a space.
        // Otherwise, initialize the row and fill indices 0 through $col-1 with spaces.
        if (!isset($this->buffer[$row])) {
            $this->buffer[$row] = [];
        }

        for ($i = 0; $i < $col; $i++) {
            if (!array_key_exists($i, $this->buffer[$row])) {
                $this->buffer[$row][$i] = ' ';
            }
        }

        // Make sure we don't splice a wide character.
        if (array_key_exists($col, $this->buffer[$row]) && $this->buffer[$row][$col] === null) {
            for ($i = $col; $i >= 0; $i--) {
                // Replace null values with a space.
                if (!isset($this->buffer[$row][$i]) || $this->buffer[$row][$i] === null) {
                    $this->buffer[$row][$i] = ' ';
                } else {
                    // Also replace the first non-null value with a space, then exit.
                    $this->buffer[$row][$i] = ' ';
                    break;
                }
            }
        }

        for ($i = 0; $i < $totalUnits; $i++) {
            $unit = $units[$i];

            // Check if the unit is a tab character.
            if ($unit === "\t") {
                // Calculate tab width as the number of spaces needed to reach the next tab stop.
                $unitWidth = 8 - ($currentCol % 8);
            } else {
                $unitWidth = Grapheme::wcwidth($unit);
            }

            // If adding this unit would overflow the available width, break out.
            if ($currentCol + $unitWidth > $this->width) {
                break;
            }

            // Write the unit into the first cell.
            $this->buffer[$row][$currentCol] = $unit;

            // Fill any additional columns that the unit occupies with PHP null.
            for ($j = 1; $j < $unitWidth; $j++) {
                if (($currentCol + $j) < $this->width) {
                    $this->buffer[$row][$currentCol + $j] = null;
                }
            }

            $currentCol += $unitWidth;

            // Clear out any leftover continuation nulls
            if (array_key_exists($currentCol, $this->buffer[$row]) && $this->buffer[$row][$currentCol] === null) {
                $k = $currentCol;

                while (array_key_exists($k, $this->buffer[$row]) && $this->buffer[$row][$k] === null) {
                    $this->buffer[$row][$k] = ' ';
                    $k++;
                }
            }

            $advanceCursor += $unitWidth;
        }

        // The remainder is the unprocessed units joined back into a string.
        $remainder = implode('', array_slice($units, $i));

        return [$advanceCursor, $remainder];
    }

    public function expand($rows)
    {
        while (count($this->buffer) <= $rows) {
            $this->buffer[] = [];
        }
    }

    protected function normalizeClearColumns(int $currentRow, int $startRow, int $startCol, int $endRow, int $endCol)
    {
        if ($startRow === $endRow) {
            $cols = [$startCol, $endCol];
        } elseif ($currentRow === $startRow) {
            $cols = [$startCol, PHP_INT_MAX];
        } elseif ($currentRow === $endRow) {
            $cols = [0, $endCol];
        } else {
            $cols = [0, PHP_INT_MAX];
        }

        return [
            max($cols[0], 0),
            min($cols[1], count($this->buffer[$currentRow]) - 1),
        ];
    }

    public function clear(
        int $startRow = 0,
        int $startCol = 0,
        int $endRow = PHP_INT_MAX,
        int $endCol = PHP_INT_MAX
    ) {
        // Short-circuit if we're clearing the whole buffer.
        if ($startRow === 0 && $startCol === 0 && $endRow === PHP_INT_MAX && $endCol === PHP_INT_MAX) {
            $this->buffer = [];

            return;
        }

        $endRow = min($endRow, count($this->buffer) - 1);

        for ($row = $startRow; $row <= $endRow; $row++) {
            if (!array_key_exists($row, $this->buffer)) {
                continue;
            }
            $cols = $this->normalizeClearColumns($row, $startRow, $startCol, $endRow, $endCol);

            $line = $this->buffer[$row];
            $length = count($this->buffer[$row]) - 1;

            if ($cols[0] === 0 && $cols[1] === $length) {
                // Clearing an entire line. Benchmarked slightly
                // faster to just replace the entire row.
                $this->buffer[$row] = [];
            } elseif ($cols[0] > 0 && $cols[1] === $length) {
                // Clearing from cols[0] to the end of the line.
                $this->buffer[$row] = array_slice($line, 0, $cols[0]);
            } else {
                // Clearing the middle of a row. Fill with either 0s or spaces.
                $this->fill(' ', $row, $cols[0], $cols[1]);
            }
        }
    }

    public function fill(mixed $value, int $row, int $startCol, int $endCol)
    {
        $this->expand($row);

        $line = $this->buffer[$row];

        $this->buffer[$row] = array_replace(
            $line, array_fill_keys(range($startCol, $endCol), $value)
        );
    }

    public function getBuffer(): array
    {
        return $this->buffer;
    }

    public function lines(): array
    {
        return array_map(static fn($row) => implode('', $row), $this->buffer);
    }
}
