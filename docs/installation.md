---
title: Installation
description: How to install and set up Solo in your Laravel application.
---

# Installation

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2 or higher |
| Laravel | 10, 11, or 12 |
| OS | Unix-like (macOS, Linux) |
| Extensions | ext-pcntl, ext-posix |

### Unix Only

Solo requires `ext-pcntl` and `ext-posix` for process management. These extensions are not available on Windows. If you're on Windows, consider using WSL2.

### GNU Screen (Recommended)

Solo works best with GNU Screen 5.0.0 or higher installed. Screen provides proper PTY allocation and improves ANSI rendering.

Check if Screen is installed:

```bash
screen --version
```

Install on macOS:

```bash
brew install screen
```

Install on Ubuntu/Debian:

```bash
sudo apt install screen
```

Solo will work without Screen, but you may experience degraded output in some commands.

## Install via Composer

Install Solo as a development dependency:

```bash
composer require soloterm/solo --dev
```

The `--dev` flag ensures Solo is only installed in development environments.

## Publish Configuration

Run the install command to publish the configuration file:

```bash
php artisan solo:install
```

This creates `config/solo.php` where you can customize commands, themes, and keybindings.

## Verify Installation

Start Solo:

```bash
php artisan solo
```

You should see the Solo interface with your configured commands. Press `q` to quit.

## Default Commands

Out of the box, Solo comes configured with:

| Tab | Command | Autostart |
|-----|---------|-----------|
| About | `php artisan solo:about` | Yes |
| Logs | Log file tailing | Yes |
| Vite | `npm run dev` | Yes |
| Make | Artisan make commands | Yes |
| Dumps | Dump server | No (lazy) |
| Reverb | WebSocket server | No (lazy) |
| Pint | Code style fixer | No (lazy) |
| Queue | Queue worker | No (lazy) |
| Tests | PHPUnit tests | No (lazy) |

Lazy commands don't auto-start. Switch to their tab and press `s` to start.

## Running with Laravel Sail

If you use Laravel Sail, prefix commands with the Sail binary:

```php
'commands' => [
    'Queue' => 'vendor/bin/sail artisan queue:work --ansi',
    'Vite' => 'vendor/bin/sail npm run dev',
],
```

## Environment Variables

Solo respects these environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `SOLO_THEME` | `dark` | Theme to use (light or dark) |
| `SOLO_KEYBINDING` | `default` | Keybinding preset |
| `SOLO_USE_SCREEN` | `true` | Use GNU Screen wrapper |
| `SOLO_DUMP_SERVER_HOST` | `tcp://127.0.0.1:9984` | Dump server address |

## Troubleshooting

### "Command not found" or autoload issues

Clear Laravel's caches:

```bash
php artisan cache:clear
composer dump-autoload
```

### Commands not showing output

1. Test the command outside of Solo first
2. Check if the command has an `--ansi` or `--colors=always` option
3. Verify the command writes to STDOUT

### Screen-related issues

If you experience rendering problems:

```bash
# Disable Screen wrapper
SOLO_USE_SCREEN=false php artisan solo
```

Or in your `.env`:

```env
SOLO_USE_SCREEN=false
```

### Port conflicts

If the dump server port is in use:

```env
SOLO_DUMP_SERVER_HOST=tcp://127.0.0.1:9985
```

## Next Steps

- [Configuration](configuration) - Customize Solo's behavior
- [Commands](commands) - Add and configure commands
- [Keybindings](keybindings) - Learn keyboard controls
