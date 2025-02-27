<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Popups;

use Laravel\Prompts\Concerns\Colors;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Support\Screen;

class Quitting extends Popup
{
    use Colors;

    public Screen $screen;

    public array $commands;

    public function setCommands(array $commands)
    {
        $this->commands = $commands;

        return $this;
    }

    public function renderSingleFrame()
    {
        $this->screen->write("\e[H\e[0J");

        $this->screen->writeln($this->bold('Stopping all processes...'));

        foreach ($this->commands as $command) {
            /** @var Command $command */
            $name = $command->name;

            if (!$command->processRunning()) {
                $name = '✓ ' . $this->dim($this->strikethrough($name));
            }

            $name .= ' ';

            $this->screen->writeln($name);
        }
    }

    public function handleInput($key)
    {
        //
    }

    public function footer()
    {
        return '';
    }

    public function shouldClose(): bool
    {
        return false;
    }
}
