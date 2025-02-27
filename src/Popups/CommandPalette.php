<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Popups;

use Generator;
use SoloTerm\Solo\Facades\Solo;
use SoloTerm\Solo\Support\CapturedMultiSelectPrompt;
use SoloTerm\Solo\Support\CapturedTextPrompt;

class CommandPalette extends Popup
{
    use HasForm;

    public bool $exitRequested = false;

    public function boot()
    {
        $this->screen->writeln('Pick all the commands you want to have in your dashboard.');
    }

    public function form(): Generator
    {
        $commands = collect(Solo::commands())->map(function ($command, $name) {
            if (is_string($command)) {
                return $name;
            }

            return $command->name ?? $name;
        });

        yield $select = new CapturedMultiSelectPrompt(
            label: 'Pick a command',
            options: $commands,
            scroll: 10,
        );

        yield 'Type in whatever you want';

        yield $random = new CapturedTextPrompt(
            label: 'Type a new command',
            placeholder: 'php artisan foo:bar'
        );

        dump($random->value());
    }

    public function handleInput($key)
    {
        if ($key === "\x18") {
            $this->exitRequested = true;

            return;
        }

        $this->handleFormInput($key);
    }

    public function shouldClose(): bool
    {
        return $this->exitRequested;
    }
}
