<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use SoloTerm\Solo\Prompt\Dashboard;
use Symfony\Component\Process\Process;

class Solo extends Command
{
    protected $signature = 'solo';

    protected $description = 'Start all the commands required to develop this application.';

    public function handle(): void
    {
        $this->monitor();
        $this->checkScreenVersion();

        Dashboard::start();
    }

    protected function checkScreenVersion(): void
    {
        if (!(bool) Config::get('solo.use_screen', true)) {
            return;
        }

        $process = new Process(['screen', '-v']);
        $process->run();

        if ($process->isSuccessful()) {
            preg_match('/Screen version ([\d.]+)/', $process->getOutput(), $matches);

            if (!empty($matches[1]) && version_compare($matches[1], '5.0.0', '<')) {
                Log::error("The installed version of `screen` ({$matches[1]}) is outdated. Please upgrade to 5.0.0 or greater for best compatibility with Solo.");
            }
        } else {
            Log::error('Unable to determine `screen` version. Make sure `screen` is installed.');
        }

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
