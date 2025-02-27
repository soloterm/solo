<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Commands\EnhancedTailCommand;
use SoloTerm\Solo\Commands\MakeCommand;

return [
    'theme' => 'light',

    'commands' => [
        'About' => 'php artisan solo:about',
        'Logs' => EnhancedTailCommand::file(storage_path('logs/laravel.log')),
        'Vite' => 'npm run dev',
        'Make' => new MakeCommand,
        // 'HTTP' => 'php artisan serve',

        // Lazy commands do no automatically start when Solo starts.
        'Dumps' => Command::from('php artisan solo:dumps')->lazy(),
        'Reverb' => Command::from('php artisan reverb')->lazy(),
        'Pint' => Command::from('./vendor/bin/pint --ansi')->lazy(),
        'Queue' => Command::from('php artisan queue:work')->lazy(),
        'Tests' => Command::from('php artisan test --colors=always')->lazy(),
    ],

];
