<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Popups;

use Laravel\Prompts\Key;
use SoloTerm\Solo\Support\Screen;

class Help extends Popup
{
    public Screen $screen;

    public bool $exitRequested = false;

    public function boot()
    {
        $this->screen->writeln('This is where the help text would go.');
    }

    public function renderSingleFrame()
    {
        //
    }

    public function handleInput($key)
    {
        if ($key === Key::ESCAPE) {
            $this->exitRequested = true;

            return;
        }
    }

    public function footer()
    {
        return 'Press ESC to close.';
    }

    public function shouldClose(): bool
    {
        return $this->exitRequested;
    }
}
