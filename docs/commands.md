---
title: Commands
description: Configure and create custom commands for Solo.
---

# Commands

Commands are the heart of Solo. Each command runs in its own tab, and you can switch between them with arrow keys.

## Defining Commands

Commands are defined in the `commands` array in `config/solo.php`:

```php
'commands' => [
    'About' => 'php artisan solo:about',
    'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log'),
    'Vite' => 'npm run dev',
    'Queue' => Command::from('php artisan queue:work')->lazy(),
],
```

The array key becomes the tab name displayed in Solo.

## Command Types

### String Commands

The simplest way to define a command:

```php
'Vite' => 'npm run dev',
'HTTP' => 'php artisan serve',
'Horizon' => 'php artisan horizon',
```

### Command Objects

For more control, use the `Command` class:

```php
use SoloTerm\Solo\Commands\Command;

'Queue' => Command::from('php artisan queue:work'),
'Tests' => Command::from('php artisan test --colors=always'),
```

### Custom Command Classes

For full control, create your own command class:

```php
'Make' => new MakeCommand,
```

## Command Modifiers

### Lazy Commands

Lazy commands don't auto-start when Solo launches:

```php
'Queue' => Command::from('php artisan queue:work')->lazy(),
'Reverb' => Command::from('php artisan reverb:start')->lazy(),
```

Switch to a lazy command's tab and press `s` to start it.

### Environment Variables

Pass environment variables to a command:

```php
'Tests' => Command::from('php artisan test --colors=always')
    ->withEnv(['APP_ENV' => 'testing'])
    ->lazy(),
```

### Working Directory

Run a command in a specific directory:

```php
'Frontend' => Command::from('npm run dev')
    ->inDirectory(base_path('frontend')),
```

### Interactive Commands

Mark a command as interactive to allow typing into it:

```php
'Tinker' => Command::from('php artisan tinker')->interactive(),
```

Press `i` to enter interactive mode, `Ctrl+X` to exit.

## Creating Custom Command Classes

For complex commands, extend the `Command` class:

```php
<?php

namespace App\Solo\Commands;

use SoloTerm\Solo\Commands\Command;

class MyCustomCommand extends Command
{
    public function __construct()
    {
        parent::__construct(
            name: 'Custom',
            command: 'my-command --option',
            autostart: true,
        );
    }

    public function boot(): void
    {
        // Called when the command is initialized
    }

    public function hotkeys(): array
    {
        // Add custom hotkeys for this command
        return [
            // 'key' => Hotkey::make('k', $handler)->label('Label'),
        ];
    }
}
```

Register your custom command:

```php
'commands' => [
    'Custom' => new \App\Solo\Commands\MyCustomCommand,
],
```

## Command Lifecycle

### Starting

- **Autostart commands** start when Solo launches
- **Lazy commands** start when you press `s` on their tab

### Stopping

Press `s` on a running command to stop it. Solo sends `SIGTERM` first, then `SIGKILL` after a timeout if the process doesn't exit.

### Restarting

Press `r` to restart the current command. This stops and then starts it again.

### Clearing

Press `c` to clear the command's output buffer.

## Output Management

### Scrolling

- **Up/Down arrows** scroll line by line
- **Shift+Up/Down** scroll by page
- **Home/End** jump to top/bottom

### Pausing

Press `p` to pause output (stop auto-scrolling). Press `f` to resume following new output.

### Following

When following is enabled (default), the display auto-scrolls as new output arrives. Scrolling up automatically pauses following.

## Tips

### Force ANSI Colors

Many commands detect they're not running in a TTY and disable colors. Force colors with flags:

```php
'Tests' => Command::from('php artisan test --colors=always')->lazy(),
'Pint' => Command::from('./vendor/bin/pint --ansi')->lazy(),
```

### Laravel Sail

For Sail projects, use the Sail binary:

```php
'Queue' => 'vendor/bin/sail artisan queue:work --ansi',
```

### Long-Running Commands

For commands that run indefinitely (queue workers, servers), make them lazy to avoid starting them when you don't need them:

```php
'Queue' => Command::from('php artisan queue:work')->lazy(),
'Horizon' => Command::from('php artisan horizon')->lazy(),
```

## Next Steps

- [Keybindings](keybindings) - Learn keyboard controls
- [Built-in Commands](built-in) - Special commands included with Solo
