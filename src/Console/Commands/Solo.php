<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;
use SoloTerm\Solo\Prompt\Dashboard;
use Symfony\Component\Process\Process;

class Solo extends Command
{
    protected $signature = 'solo';

    protected $description = 'Start all the commands required to develop this application.';

    public function handle(): void
    {
        $this->monitor();

        Dashboard::start();
    }

    protected function monitor()
    {
        $process = new Process(['php', 'artisan', 'solo:monitor', getmypid()]);

        // Ensure the process runs in the background and doesn't tie to the parent
        $process->setOptions([
            'create_new_console' => true,
            'create_process_group' => true,
        ]);

        $process->disableOutput();

        $process->start();
    }
}
