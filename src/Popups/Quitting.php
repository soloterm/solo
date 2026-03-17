<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Popups;

use Laravel\Prompts\Concerns\Colors;
use SoloTerm\Screen\Screen;
use SoloTerm\Solo\Commands\Command;

class Quitting extends Popup
{
    use Colors;

    public Screen $screen;

    /** @var array<int, Command> */
    public array $commands = [];

    /**
     * @param  array<int, Command>  $commands
     */
    public function setCommands(array $commands): static
    {
        $this->commands = $commands;

        return $this;
    }

    public function renderSingleFrame(): void
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

    public function handleInput(string $key): void
    {
        //
    }

    public function footer(): string
    {
        return '';
    }

    public function shouldClose(): bool
    {
        return false;
    }
}
