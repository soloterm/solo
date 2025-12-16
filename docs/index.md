---
title: Introduction
description: Run multiple Laravel development commands in a unified tabbed terminal interface.
---

# Solo for Laravel

Solo is a Laravel package that runs multiple development commands simultaneously in a unified, tabbed terminal interface. Instead of juggling multiple terminal windows for Vite, logs, queues, and tests, run everything with a single command:

```bash
php artisan solo
```

## The Problem

Modern Laravel development requires running multiple processes:

- **Vite** for frontend asset compilation
- **Queue worker** for background jobs
- **Log tailing** for debugging
- **Reverb** for WebSockets
- **Tests** during development

Each process needs its own terminal window. Switch between them constantly. Lose track of which window has what. It's chaos.

## The Solution

Solo consolidates everything into one terminal:

```
┌─────────────────────────────────────────────────────────────────┐
│ [About] [Logs] [Vite] [Make] [Dumps] [Queue] [Tests]           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Vite is running at http://localhost:5173/                    │
│                                                                 │
│   LARAVEL v11.0.0  plugin v1.0.0                               │
│                                                                 │
│   ➜  Local:   http://localhost:5173/                           │
│   ➜  Network: http://192.168.1.100:5173/                       │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ c Clear  p Pause  s Stop  r Restart  q Quit  ←/→ Prev/Next    │
└─────────────────────────────────────────────────────────────────┘
```

Each command runs in its own tab. Switch tabs with arrow keys. All your development tools in one place.

## Key Features

### Tabbed Interface

Every command gets its own tab. Switch instantly with left/right arrow keys or press `g` to jump to any tab.

### Lazy Commands

Not every command needs to run all the time. Mark commands as "lazy" and they won't auto-start:

```php
'Queue' => Command::from('php artisan queue:work')->lazy(),
```

Press `s` to start lazy commands when you need them.

### Interactive Mode

Need to type into a command? Press `i` to enter interactive mode, then `Ctrl+X` to exit. Perfect for commands that require input.

### Log Enhancement

The built-in `EnhancedTailCommand` provides smart log viewing:

- Collapsible vendor frames
- Stack trace formatting
- File truncation

### Dump Server

Intercepts `dump()` calls from your application and displays them in Solo instead of polluting your browser or API responses.

### Themes

Light and dark themes included. Create your own for a personalized experience.

### Vim Keybindings

For Vim users, switch to vim-style navigation with `h/j/k/l` keys.

## Quick Start

Install Solo:

```bash
composer require soloterm/solo --dev
php artisan solo:install
```

Start Solo:

```bash
php artisan solo
```

Navigate with arrow keys. Press `q` to quit.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2 or higher |
| Laravel | 10, 11, or 12 |
| OS | Unix-like (macOS, Linux) |
| Extensions | ext-pcntl, ext-posix |

Solo uses Unix process control features and cannot run on Windows.

## Next Steps

- [Installation](installation) - Complete setup guide
- [Configuration](configuration) - Customize Solo for your workflow
- [Commands](commands) - Configure and create commands
