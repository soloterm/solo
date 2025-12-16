---
title: Configuration
description: Complete reference for Solo's configuration options.
---

# Configuration

Solo is configured through `config/solo.php`. This file is published when you run `php artisan solo:install`.

## Full Configuration Reference

```php
<?php

use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Commands\EnhancedTailCommand;
use SoloTerm\Solo\Commands\MakeCommand;
use SoloTerm\Solo\Hotkeys;
use SoloTerm\Solo\Themes;

return [
    /*
    |--------------------------------------------------------------------------
    | Theme
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
    */
    'commands' => [
        'About' => 'php artisan solo:about',
        'Logs' => EnhancedTailCommand::file(storage_path('logs/laravel.log')),
        'Vite' => 'npm run dev',
        'Make' => new MakeCommand,

        // Lazy commands don't auto-start
        'Dumps' => Command::from('php artisan solo:dumps')->lazy(),
        'Queue' => Command::from('php artisan queue:work')->lazy(),
    ],

    /*
    |--------------------------------------------------------------------------
    | GNU Screen
    |--------------------------------------------------------------------------
    */
    'use_screen' => (bool) env('SOLO_USE_SCREEN', true),

    /*
    |--------------------------------------------------------------------------
    | Dump Server
    |--------------------------------------------------------------------------
    */
    'dump_server_host' => env('SOLO_DUMP_SERVER_HOST', 'tcp://127.0.0.1:9984'),
];
```

## Configuration Options

### theme

```php
'theme' => env('SOLO_THEME', 'dark'),
```

The active theme. Solo ships with `light` and `dark` themes. Set via the `SOLO_THEME` environment variable or directly in config.

### themes

```php
'themes' => [
    'light' => Themes\LightTheme::class,
    'dark' => Themes\DarkTheme::class,
],
```

Available themes. Add your own custom theme classes here. See [Themes](themes) for details.

### keybinding

```php
'keybinding' => env('SOLO_KEYBINDING', 'default'),
```

The active keybinding preset. Options are `default` or `vim`. Set via the `SOLO_KEYBINDING` environment variable.

### keybindings

```php
'keybindings' => [
    'default' => Hotkeys\DefaultHotkeys::class,
    'vim' => Hotkeys\VimHotkeys::class,
],
```

Available keybinding presets. Add your own custom keybinding classes here. See [Keybindings](keybindings) for details.

### commands

```php
'commands' => [
    'About' => 'php artisan solo:about',
    // ...
],
```

The commands that appear as tabs in Solo. The array key becomes the tab name. See [Commands](commands) for all the ways to define commands.

### use_screen

```php
'use_screen' => (bool) env('SOLO_USE_SCREEN', true),
```

Whether to use GNU Screen as a wrapper for commands. Screen provides proper PTY allocation and better ANSI rendering. Disable if Screen isn't installed or causes issues.

### dump_server_host

```php
'dump_server_host' => env('SOLO_DUMP_SERVER_HOST', 'tcp://127.0.0.1:9984'),
```

The TCP address for the dump server. Change if port 9984 conflicts with another service.

## Environment Variables

All Solo settings can be configured via environment variables:

```env
# Theme
SOLO_THEME=dark

# Keybindings
SOLO_KEYBINDING=vim

# GNU Screen
SOLO_USE_SCREEN=true

# Dump server
SOLO_DUMP_SERVER_HOST=tcp://127.0.0.1:9984
```

## Production Safety

The configuration file includes a safety check:

```php
if (!class_exists('\SoloTerm\Solo\Manager')) {
    return [];
}
```

This ensures the config file doesn't cause errors if Solo isn't installed (e.g., in production where it's a dev dependency).

## Next Steps

- [Commands](commands) - Configure and create commands
- [Keybindings](keybindings) - Customize keyboard controls
- [Themes](themes) - Customize appearance
