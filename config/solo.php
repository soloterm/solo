<?php

use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Commands\MakeCommand;
use SoloTerm\Solo\Commands\TestCommand;
use SoloTerm\Solo\Hotkeys;
use SoloTerm\Solo\Themes;

// Solo may not (should not!) exist in prod, so we have to
// check here first to see if it's installed.
if (!class_exists('\SoloTerm\Solo\Manager')) {
    return [
        //
    ];
}

return [
    /*
    |--------------------------------------------------------------------------
    | Themes
    |--------------------------------------------------------------------------
    */
    'theme' => env('SOLO_THEME', 'dark'),

    'themes' => [
        'light' => Themes\LightTheme::class,
        'dark' => Themes\DarkTheme::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Keybindings
    |--------------------------------------------------------------------------
    */
    'keybinding' => env('SOLO_KEYBINDING', 'default'),

    'keybindings' => [
        'default' => Hotkeys\DefaultHotkeys::class,
        'vim' => Hotkeys\VimHotkeys::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    */
    'commands' => [
        'About' => 'php artisan solo:about',
        // For enhanced log viewing with vendor frame collapsing, see soloterm/vtail
        'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log'),
        'Vite' => 'npm run dev',
        'Make' => new MakeCommand,
        // 'HTTP' => 'php artisan serve',

        // Lazy commands do not automatically start when Solo starts.
        'Dumps' => Command::from('php artisan solo:dumps')->lazy(),
        'Reverb' => Command::from('php artisan reverb:start --debug')->lazy(),
        'Pint' => Command::from('./vendor/bin/pint --ansi')->lazy(),
        'Queue' => Command::from('php artisan queue:work')->lazy(),
        'Tests' => TestCommand::artisan(),
    ],

    /**
     * Process driver used to execute commands.
     *
     * Supported values:
     * - native: Run directly in Solo's PTY (default, no external dependencies)
     * - screen: Wrap commands with GNU Screen (deprecated, will be removed)
     *
     * You should not need to change this. The native driver handles PTY
     * allocation, ANSI passthrough, and UTF-8 without GNU Screen.
     */
    'process_driver' => env('SOLO_PROCESS_DRIVER'),

    /**
     * @deprecated Use `process_driver` instead. This option will be removed in a future release.
     */
    'use_screen' => (bool) env('SOLO_USE_SCREEN', false),

    /*
    |--------------------------------------------------------------------------
    | Miscellaneous
    |--------------------------------------------------------------------------
    */

    /*
     * If you run the solo:dumps command, Solo will start a server to receive
     * the dumps. This is the address. You probably don't need to change
     * this unless the default is already taken for some reason.
     */
    'dump_server_host' => env('SOLO_DUMP_SERVER_HOST', 'tcp://127.0.0.1:9984')
];
