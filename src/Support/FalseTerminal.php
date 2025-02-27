<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\Terminal;

class FalseTerminal extends Terminal
{
    public $width = 80;

    public $height = 24;

    public function __construct()
    {
        //
    }

    public function read(): string
    {
        return '';
    }

    public function setTty(string $mode): void
    {
        //
    }

    public function restoreTty(): void
    {
        //
    }

    public function cols(): int
    {
        return $this->width;
    }

    public function lines(): int
    {
        return $this->height;
    }

    public function initDimensions(): void
    {
        //
    }

    public function exit(): void
    {
        //
    }

    protected function exec(string $command): string
    {
        return '';
    }
}
