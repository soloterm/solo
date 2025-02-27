<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;

class Install extends Command
{
    protected $signature = 'solo:install';

    protected $description = 'Install the Solo service provider';

    public function handle()
    {
        $this->comment('Publishing Solo configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'solo-config']);

        $this->info('Solo installed successfully.');
        $this->info('Run `php artisan solo` to start.');
    }
}
