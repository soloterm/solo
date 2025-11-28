<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\SearchPrompt;

class CapturedSearchPrompt extends SearchPrompt implements CapturedPromptInterface
{
    use CapturedPrompt;
}
