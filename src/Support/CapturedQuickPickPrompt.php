<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\Themes\Default\SearchPromptRenderer;

class CapturedQuickPickPrompt extends QuickPickPrompt implements CapturedPromptInterface
{
    use CapturedPrompt;

    protected function rendererClass(): string
    {
        return SearchPromptRenderer::class;
    }
}
