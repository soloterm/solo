<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\Themes\Default\SuggestPromptRenderer;

class CapturedSuggestPrompt extends SuggestPrompt implements CapturedPromptInterface
{
    use CapturedPrompt;

    protected function rendererClass(): string
    {
        return SuggestPromptRenderer::class;
    }
}
