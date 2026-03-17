<?php

declare(strict_types=1);

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

class Frames
{
    protected int $current = 0;

    public function next(): void
    {
        $this->current++;
    }

    public function current(int $buffer = 1): int
    {
        return (int) floor($this->current / $buffer);
    }

    /**
     * @param  array<int, string>  $frames
     */
    public function frame(array $frames, int $buffer = 1): string
    {
        return $frames[$this->current($buffer) % count($frames)];
    }
}
