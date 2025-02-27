<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Buffers;

use InvalidArgumentException;
use RuntimeException;

class AnsiBuffer extends Buffer
{
    protected mixed $valueForClearing = 0;

    /**
     * The active integer bitmask representing current standard ANSI
     * states like bold, underline, 8-color FG/BG, etc.
     */
    protected int $active = 0;

    /**
     * Extended active color states:
     *   [ 'type' => '256', 'color' => <0–255> ] or
     *   [ 'type' => 'rgb',  'r' => <0–255>, 'g' => <0–255>, 'b' => <0–255> ]
     */
    protected ?array $extendedForeground = null;

    protected ?array $extendedBackground = null;

    /**
     * The ANSI codes that control text decoration,
     * like underline, bold, italic, etc.
     *
     * @var array<int>
     */
    protected readonly array $decoration;

    /**
     * The ANSI codes that control decoration resets.
     *
     * @var array<int>
     */
    protected readonly array $resets;

    /**
     * The ANSI codes that control text foreground colors.
     *
     * @var array<int>
     */
    protected readonly array $foreground;

    /**
     * The ANSI codes that control text background colors.
     *
     * @var array<int>
     */
    protected readonly array $background;

    /**
     * The keys are ANSI SGR codes and the values are arbitrary bit values
     * initiated in the constructor allowing for efficient combination
     * and manipulation using bitwise operations.
     *
     * @link https://gist.github.com/fnky/458719343aabd01cfb17a3a4f7296797
     *
     * @var array<int, int>
     */
    protected readonly array $codes;

    /**
     * Each key is an ANSI code that turns a certain decoration on, while
     * the value is an ANSI code that turns that decoration off.
     *
     * @var int[]
     */
    protected array $decorationResets = [
        1 => 22, // 22 does indeed turn off bold & dim.
        2 => 22, // 22 does indeed turn off bold & dim.
        3 => 23,
        4 => 24,
        5 => 25,
        7 => 27,
        8 => 28,
        9 => 29,
    ];

    public function initialize()
    {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException(static::class . ' requires a 64-bit PHP environment.');
        }

        // https://gist.github.com/fnky/458719343aabd01cfb17a3a4f7296797
        $this->decoration = range(1, 9);
        $this->resets = range(22, 29);
        // Standard and bright.
        $this->foreground = [...range(30, 39), ...range(90, 97)];
        $this->background = [...range(40, 49), ...range(100, 107)];

        $supported = [
            0, // Reset all styles
            ...$this->decoration,
            ...$this->resets,
            ...$this->foreground,
            ...$this->background,
        ];

        $this->codes = array_reduce($supported, function ($carry, $code) {
            // Every code gets a unique bit value, via left shift.
            $carry[$code] = 1 << count($carry);

            return $carry;
        }, []);
    }

    /**
     * The "cellValue" determines what we actually store in the Buffer:
     * - If no extended FG or BG, store just an int (the $this->active bitmask).
     * - If extended FG or BG is set, store an array with bits + extFg + extBg.
     */
    protected function cellValue(): int|array
    {
        if (is_null($this->extendedForeground) && is_null($this->extendedBackground)) {
            return $this->active;
        }

        // Use conventional placement to avoid having named keys, since
        // it could be copied many tens of thousands of times.
        return [
            $this->active, $this->extendedForeground, $this->extendedBackground,
        ];
    }

    public function fillBufferWithActiveFlags(int $row, int $startCol, int $endCol): void
    {
        $this->fill($this->cellValue(), $row, $startCol, $endCol);
    }

    public function getActive(): int
    {
        return $this->active;
    }

    public function getActiveAsAnsi(): string
    {
        return $this->ansiStringFromBits($this->active);
    }

    public function getActiveBackground(): array|int
    {
        // If we have an extended background (256-color or RGB), that counts as "active"
        if ($this->extendedBackground !== null) {
            return $this->extendedBackground;
        }

        // Build a bitmask that represents all possible background codes.
        // Then see if any of those bits are set in $this->active.
        $backgroundBitmask = 0;
        foreach ($this->background as $code) {
            $backgroundBitmask |= $this->codes[$code];
        }

        // If any background bits are set, this expression will be non-zero.
        return $this->active & $backgroundBitmask;
    }

    public function compressedAnsiBuffer(): array
    {
        $lines = $this->buffer;

        return array_map(function ($line) {
            // We reset the previous cell on every single line, because we're not guaranteed that the
            // whole buffer is going to be printed to the screen. Imagine the first line has some
            // color information, like set foreground to blue. Then there are 300 lines of text.

            // In Solo, only ~50 lines are going to be shown. If the first line (the one that contains
            // the color info) is not shown, then the next line will have the wrong color. We need
            // to reset the color on every line, even if it's the same as the previous line.

            // Conventional placement: bits, extfg, extbg.
            $previousCell = [0, null, null];

            return array_filter(array_map(function ($cell) use (&$previousCell) {
                if (is_int($cell)) {
                    $cell = [$cell, null, null];
                }

                $uniqueBits = $cell[0] & ~$previousCell[0];
                $turnedOffBits = $previousCell[0] & ~$cell[0];

                $resetCodes = [];
                $turnedOffCodes = $this->ansiCodesFromBits($turnedOffBits);

                foreach ($turnedOffCodes as $code) {
                    if ($this->codeInRange($code, $this->foreground)) {
                        // If a foreground code was removed, then use code 39 to reset.
                        $resetCodes[] = 39;
                    } elseif ($this->codeInRange($code, $this->background)) {
                        // If a background code was removed, then use code 49 to reset.
                        $resetCodes[] = 49;
                    } elseif ($this->codeInRange($code, $this->decoration) && isset($this->decorationResets[$code])) {
                        // If a decoration code turned off, apply its reset
                        $resetCodes[] = $this->decorationResets[$code];
                    }
                }

                $uniqueCodes = $this->ansiCodesFromBits($uniqueBits);

                // Extended foreground changed
                if ($previousCell[1] !== $cell[1]) {
                    if ($previousCell[1] !== null && $cell[1] === null) {
                        $resetCodes[] = 39;
                    } elseif ($cell[1] !== null) {
                        $uniqueCodes[] = $this->buildExtendedColorCode(38, $cell[1]);
                    }
                }

                // Extended background changed
                if ($previousCell[2] !== $cell[2]) {
                    if ($previousCell[2] !== null && $cell[2] === null) {
                        $resetCodes[] = 49;
                    } elseif ($cell[2] !== null) {
                        $uniqueCodes[] = $this->buildExtendedColorCode(48, $cell[2]);
                    }
                }

                $previousCell = $cell;

                return $this->ansiStringFromCodes(array_unique(array_merge($resetCodes, $uniqueCodes)));
            }, $line));
        }, $lines);
    }

    public function ansiCodesFromBits(int $bits): array
    {
        // Short-circuit
        if ($bits === 0) {
            return [];
        }

        $active = [];

        foreach ($this->codes as $code => $bit) {
            // Because the bits grow in ascending powers of 2,
            // if $bit > $bits, we can break early.
            if ($bit > $bits) {
                break;
            }

            if (($bits & $bit) === $bit) {
                $active[] = $code;
            }
        }

        sort($active);

        return $active;
    }

    public function ansiStringFromCodes(array $codes): string
    {
        return count($codes) ? ("\e[" . implode(';', $codes) . 'm') : '';
    }

    public function ansiStringFromBits(int $bits): string
    {
        // Basic codes from the bitmask
        $codes = $this->ansiCodesFromBits($bits);

        // If we have an extended FG, add that
        if ($this->extendedForeground !== null) {
            $codes[] = $this->buildExtendedColorCode(38, $this->extendedForeground);
        }
        // If we have an extended BG, add that
        if ($this->extendedBackground !== null) {
            $codes[] = $this->buildExtendedColorCode(48, $this->extendedBackground);
        }

        return $this->ansiStringFromCodes($codes);
    }

    public function addAnsiCodes(int ...$codes)
    {
        for ($i = 0; $i < count($codes); $i++) {
            $code = $codes[$i];

            // Extended color codes are multipart
            // https://gist.github.com/fnky/458719343aabd01cfb17a3a4f7296797#256-colors
            if ($code === 38 || $code === 48) {
                // Code 2 = RGB colors
                // Code 5 = 256 colors
                $type = $codes[$i + 1] ?? null;

                if ($type === 2 || $type === 5) {
                    // A 256 color type requires 1 additional code, an RGB type requires 3.
                    $take = $type === 5 ? 1 : 3;

                    // Take the type code too
                    $take++;

                    $slice = array_slice($codes, $i + 1, $take);

                    if (count($slice) < $take) {
                        // Not enough codes... just move on
                        continue;
                    }

                    $this->setExtendedColor($code, $slice);

                    $i += $take;
                }

                continue;
            }

            // Otherwise treat it as a normal code
            $this->addAnsiCode($code);
        }
    }

    public function addAnsiCode(int $code)
    {
        if (!array_key_exists($code, $this->codes)) {
            return;
            // throw new InvalidArgumentException("Invalid ANSI code: $code");
        }

        // Reset all styles.
        if ($code === 0) {
            $this->resetBitRange(0, 64);
            $this->extendedForeground = null;
            $this->extendedBackground = null;
        }

        // If we're adding a new foreground color, zero out the old ones.
        if ($this->codeInRange($code, $this->foreground)) {
            $this->resetForeground();
        }

        // Same for backgrounds.
        if ($this->codeInRange($code, $this->background)) {
            $this->resetBackground();
        }

        // If we're adding a decoration, we need to unset the
        // code that disables that specific decoration.
        if ($this->codeInRange($code, $this->decoration) && isset($this->decorationResets[$code])) {
            $bitToUnset = $this->decorationResets[$code] ?? null;
            if (isset($this->codes[$bitToUnset])) {
                $this->active &= ~$this->codes[$bitToUnset];
            }
        }

        // If we're unsetting a decoration, we need to remove
        // the code that enables that decoration.
        if ($this->codeInRange($code, $this->resets)) {
            $unset = 0;
            foreach ($this->decorationResets as $decoration => $reset) {
                if ($code === $reset) {
                    $unset |= $this->codes[$decoration];
                }
            }
            $this->active &= ~$unset;
        }

        $this->active |= $this->codes[$code];
    }

    protected function setExtendedColor(int $baseCode, array $color)
    {
        if ($baseCode === 38) {
            $this->resetForeground();
            $this->extendedForeground = $color;
        } elseif ($baseCode === 48) {
            $this->resetBackground();
            $this->extendedBackground = $color;
        }
    }

    protected function buildExtendedColorCode(int $base, array $color): string
    {
        return implode(';', [$base, ...$color]);
    }

    protected function resetForeground()
    {
        $this->resetCodes($this->foreground);
        $this->extendedForeground = null;
    }

    protected function resetBackground()
    {
        $this->resetCodes($this->background);
        $this->extendedBackground = null;
    }

    protected function codeInRange(int $code, array $range)
    {
        // O(1) lookup vs in_array which is O(n)
        return isset(array_flip($range)[$code]);
    }

    protected function resetCodes($codes)
    {
        foreach ($codes as $code) {
            if (isset($this->codes[$code])) {
                $this->active &= ~$this->codes[$code];
            }
        }
    }

    protected function resetBitRange(int $start, int $end): void
    {
        // Validate bit positions
        if ($start < 0 || $end < 0) {
            throw new InvalidArgumentException('Bit positions must be non-negative.');
        }

        if ($start > $end) {
            throw new InvalidArgumentException('Start bit must be less than or equal to end bit.');
        }

        $totalBits = 64;

        // Adjust end if it exceeds the total bits
        $end = min($end, $totalBits - 1);

        // Calculate the number of bits to clear
        $length = $end - $start + 1;

        // Handle cases where the length equals the integer size
        if ($length >= $totalBits) {
            // Clear all bits
            $this->active = 0;

            return;
        }

        // Create a mask with 1s outside the range and 0s within the range
        $mask = ~(((1 << $length) - 1) << $start);

        // Apply the mask to clear the specified bit range
        $this->active &= $mask;
    }
}
