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
        // Send multiple Ctrl+C signals to exit potentially nested prompts.
        // Each prompt may need its own signal to close properly.
        for ($i = 0; $i < 3; $i++) {
            if ($this->input->isClosed()) {
                break;
            }
            try {
                $this->input->write(Key::CTRL_C);
            } catch (\RuntimeException $e) {
                // Stream closed between check and write; exit gracefully
                break;
            }
        }
    }
}
