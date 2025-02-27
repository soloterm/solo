<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Buffers;

use ArrayAccess;
use ReturnTypeWillChange;

class Buffer implements ArrayAccess
{
    public array $buffer = [];

    protected mixed $valueForClearing = 0;

    public function __construct(public int $max = 5000)
    {
        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }

    public function getBuffer()
    {
        return $this->buffer;
    }

    public function clear(
        int $startRow = 0,
        int $startCol = 0,
        int $endRow = PHP_INT_MAX,
        int $endCol = PHP_INT_MAX
    ): void {
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
            $length = $this->rowMax($row);

            if ($cols[0] === 0 && $cols[1] === $length) {
                // Clearing an entire line. Benchmarked slightly
                // faster to just replace the entire row.
                $this->buffer[$row] = [];
            } elseif ($cols[0] > 0 && $cols[1] === $length) {
                // Clearing from cols[0] to the end of the line.
                // Chop off the end of the array.
                $this->buffer[$row] = array_slice($line, 0, $cols[0]);
            } else {
                // Clearing the middle of a row. Fill with the replacement value.
                $this->fill($this->valueForClearing, $row, $cols[0], $cols[1]);
            }
        }
    }

    public function expand($rows)
    {
        while (count($this->buffer) <= $rows) {
            $this->buffer[] = [];
        }
    }

    public function fill(mixed $value, int $row, int $startCol, int $endCol)
    {
        $this->expand($row);

        $line = $this->buffer[$row];

        $this->buffer[$row] = array_replace(
            $line, array_fill_keys(range($startCol, $endCol), $value)
        );

        $this->trim();
    }

    public function rowMax($row)
    {
        return count($this->buffer[$row]) - 1;
    }

    public function trim()
    {
        // 95% chance of just doing nothing.
        if (rand(1, 100) <= 95) {
            return;
        }

        $excess = count($this->buffer) - $this->max;

        // Clear out old rows. Hopefully this helps save memory.
        // @link https://github.com/aarondfrancis/solo/issues/33
        if ($excess > 0) {
            $keys = array_keys($this->buffer);
            $remove = array_slice($keys, 0, $excess);
            $nulls = array_fill_keys($remove, []);

            $this->buffer = array_replace($this->buffer, $nulls);
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
            min($cols[1], $this->rowMax($currentRow)),
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->buffer[$offset]);
    }

    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset)
    {
        return $this->buffer[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->buffer[] = $value;
        } else {
            $this->buffer[$offset] = $value;
        }

        $this->trim();
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->buffer[$offset]);
    }
}
