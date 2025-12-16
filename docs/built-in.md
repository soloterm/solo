---
title: Built-in Commands
description: Special commands included with Solo.
---

# Built-in Commands

Solo includes several specialized commands that provide enhanced functionality beyond simple command execution.

## MakeCommand

A universal entry point to all Laravel `make:*` Artisan commands.

### Usage

```php
'Make' => new MakeCommand,
```

### How It Works

When you switch to the Make tab and press `s` to start, MakeCommand prompts you for what you want to create and proxies to the appropriate `make:*` command.

This is equivalent to running `php artisan solo:make` directly.

### Supported Make Commands

All Laravel make commands are supported:

- `make:controller`
- `make:model`
- `make:migration`
- `make:middleware`
- `make:request`
- `make:resource`
- `make:event`
- `make:listener`
- `make:job`
- `make:mail`
- `make:notification`
- `make:policy`
- `make:provider`
- `make:rule`
- `make:seeder`
- `make:test`
- And more...

## Dump Server

Intercepts `dump()` calls from your application and displays them in Solo.

### Usage

```php
'Dumps' => Command::from('php artisan solo:dumps')->lazy(),
```

### How It Works

When the dump server is running, any `dump()` call in your application sends output to Solo instead of:

- Polluting browser responses
- Breaking JSON API output
- Getting lost in background jobs

### Starting the Dump Server

The dump server is typically configured as a lazy command. Switch to its tab and press `s` to start.

You'll see:

```
Listening for dumps on tcp://127.0.0.1:9984
```

### Using dump()

Once the server is running, use `dump()` anywhere:

```php
// In a controller
public function show(User $user)
{
    dump($user);  // Appears in Solo, not browser
    dump($user->orders);

    return response()->json($user);
}
```

Each dump shows its source location:

```
app/Http/Controllers/UserController.php:24
App\Models\User {#1234
  id: 1,
  name: "John Doe",
}
```

### Configuration

Change the dump server address if needed:

```env
SOLO_DUMP_SERVER_HOST=tcp://127.0.0.1:9985
```

### Standalone Package

The dump server functionality is also available as a standalone package:

```bash
composer require soloterm/dumps --dev
```

See [Dumps documentation](/docs/dumps) for more details.

## About Command

Displays Solo version and system information.

### Usage

```php
'About' => 'php artisan solo:about',
```

Shows:

- Solo version
- PHP version
- Laravel version
- System information

## Creating Your Own Built-in Commands

You can create your own custom command classes:

```php
<?php

namespace App\Solo\Commands;

use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Hotkeys\Hotkey;

class MySpecialCommand extends Command
{
    protected bool $someOption = false;

    public function __construct()
    {
        parent::__construct(
            name: 'Special',
            command: 'my-command',
            autostart: true,
        );
    }

    public function hotkeys(): array
    {
        return [
            'toggle' => Hotkey::make('o', function() {
                $this->someOption = !$this->someOption;
            })->label($this->someOption ? 'Disable' : 'Enable'),
        ];
    }

    protected function modifyWrappedLines($lines)
    {
        // Transform output before display
        if ($this->someOption) {
            return $lines->map(fn($line) => strtoupper($line));
        }

        return $lines;
    }
}
```

## Next Steps

- [Commands](commands) - Configure and create commands
- [Architecture](architecture) - Understand how Solo works
