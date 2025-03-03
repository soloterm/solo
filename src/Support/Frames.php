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

    public function next()
    {
        $this->current++;
    }

    public function current($buffer = 1)
    {
        return floor($this->current / $buffer);
    }

    public function frame(array $frames, $buffer = 1)
    {
        return $frames[$this->current($buffer) % count($frames)];
    }
}
