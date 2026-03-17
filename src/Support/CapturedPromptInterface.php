<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use SoloTerm\Screen\Screen;

interface CapturedPromptInterface
{
    public function setScreen(Screen $screen): void;

    public function isComplete(): bool;

    public function renderSingleFrame(): void;

    public function handleInput(string $key): void;
}
