<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Commands;

use Laravel\Prompts\Key;

class MakeCommand extends Command
{
    public function boot(): void
    {
        $this->name = 'Make';
        $this->command = 'php artisan solo:make';
        $this->interactive = true;
        $this->autostart = true;
    }

    public function whenStopping()
    {
        $this->potentiallyExitOpenPrompts();
        $this->potentiallyExitOpenPrompts();
        $this->potentiallyExitOpenPrompts();
    }

    protected function potentiallyExitOpenPrompts()
    {
        if (!$this->input->isClosed()) {
            $this->input->write(Key::CTRL_C);
        }
    }
}
