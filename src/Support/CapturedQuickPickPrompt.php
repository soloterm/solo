<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\Key;

class CapturedQuickPickPrompt extends QuickPickPrompt implements CapturedPromptInterface
{
    use CapturedPrompt;

    public function handleInput($key)
    {
        $continue = $this->callNativeKeyPressHandler($key);

        if ($continue === false || $key === Key::CTRL_C) {
            $this->complete = true;
            $this->clearListeners();

            if ($key === Key::CTRL_C) {
                // @TODO Cancel
            }

            if ($key === Key::CTRL_U) {
                // @TODO Revert
            }
        }
    }
}
