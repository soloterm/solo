<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

interface CapturedPromptInterface
{
    public function setScreen(Screen $screen);

    public function renderSingleFrame();

    public function handleInput($key);
}
