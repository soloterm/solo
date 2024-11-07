<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Console\Commands;

use AaronFrancis\Solo\Prompt\Dashboard;
use Illuminate\Console\Command;

class Solo extends Command
{
    protected $signature = 'solo';

    protected $description = 'Start all the commands required to develop this application.';

    protected function configure(): void
    {
        $this->setAliases([
            'dev',
        ]);

        parent::configure();
    }
    public function handle(): void
    {
        Dashboard::start();
    }
}
