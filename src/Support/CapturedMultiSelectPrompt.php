<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Themes\Default\MultiSelectPromptRenderer;

class CapturedMultiSelectPrompt extends MultiSelectPrompt implements CapturedPromptInterface
{
    use CapturedPrompt;

    protected function rendererClass(): string
    {
        return MultiSelectPromptRenderer::class;
    }
}
