<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace SoloTerm\Solo;

class CharacterBuffer
{

    protected array $buffer;
    protected int $width;
    protected int $height;

    /**
     * Create a new TerminalBuffer with the given dimensions.
     *
     * @param  int  $width  Number of columns.
     * @param  int  $height  Number of rows.
     */
    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->initializeBuffer();
    }

    /**
     * Initializes the buffer with space characters.
     */
    protected function initializeBuffer(): void
    {
        $this->buffer = [];
        for ($row = 0; $row < $this->height; $row++) {
            // Each row is an array with one element per column.
            // We initialize each cell with a space.
            $this->buffer[$row] = array_fill(0, $this->width, ' ');
        }
    }

    /**
     * Writes a string into the buffer at the specified row and starting column.
     * The string is split into grapheme clusters, and each grapheme is inserted
     * into one or more cells based on its display width. If a grapheme has width > 1,
     * its first cell gets the grapheme, and the remaining cells are set to PHP null.
     *
     * @param  int  $row  Row index (0-based).
     * @param  int  $col  Starting column index (0-based).
     * @param  string  $text  The text to write.
     *
     * @throws \Exception if splitting into graphemes fails.
     */
    public function writeString(int $row, int $col, string $text): void
    {
        if (preg_match_all('/\X/u', $text, $matches) === false) {
            throw new \Exception("Error splitting text into grapheme clusters.");
        }
        $graphemes = $matches[0];
        $currentCol = $col;
        foreach ($graphemes as $g) {
            $gWidth = mb_strwidth($g, 'UTF-8');
            if ($currentCol >= $this->width) {
                break;
            }
            // Place the grapheme in the first cell it occupies.
            $this->buffer[$row][$currentCol] = $g;
            // For any additional columns that this grapheme occupies,
            // set the cell to PHP null.
            for ($i = 1; $i < $gWidth; $i++) {
                if (($currentCol + $i) < $this->width) {
                    $this->buffer[$row][$currentCol + $i] = null;
                }
            }
            $currentCol += $gWidth;
        }
    }

    /**
     * Returns the entire buffer as a two-dimensional array.
     *
     * @return array
     */
    public function getBuffer(): array
    {
        return $this->buffer;
    }

    /**
     * Renders the buffer as a string.
     * This method simply implodes each row into a string.
     * PHP will convert any null values to empty strings.
     *
     * @return string
     */
    public function render(): string
    {
        $lines = [];
        foreach ($this->buffer as $row) {
            // Imploding the row; null values become empty strings.
            $lines[] = implode('', $row);
        }
        return implode("\n", $lines);
    }
}