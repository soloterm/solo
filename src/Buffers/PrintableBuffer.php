<?php

namespace SoloTerm\Solo\Buffers;

use Exception;
use SoloTerm\Grapheme\Grapheme;

class PrintableBuffer extends Buffer
{
    public int $width;

    protected mixed $valueForClearing = ' ';

    public function setWidth(int $width): static
    {
        $this->width = $width;

        return $this;
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

    public function lines(): array
    {
        return array_map(fn($line) => implode('', $line), $this->buffer);
    }
}
