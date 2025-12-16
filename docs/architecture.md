---
title: Architecture
description: How Solo works under the hood.
---

# Architecture

Understanding Solo's architecture helps you extend it effectively and troubleshoot issues.

## Overview

Solo is built on three main components:

1. **Dashboard** - The main TUI controller
2. **Commands** - Individual tab processes
3. **Renderer** - Frame generation and output

```
php artisan solo
       │
       ▼
   Dashboard
       │
       ├── Command 1 (About)
       ├── Command 2 (Logs)
       ├── Command 3 (Vite)
       └── Command N (...)
       │
       ▼
   Renderer → Terminal
```

## Event Loop

Solo runs at approximately 40 FPS (25ms intervals). Each tick:

1. **Poll keyboard input** - Check for key presses
2. **Tick all commands** - Collect output from subprocesses
3. **Render frame** - Generate and display the current view

```php
// Simplified event loop
while ($running) {
    $this->handleKeyPress();

    foreach ($this->commands as $command) {
        $command->onTick();
    }

    $this->render();

    usleep(25000);  // 25ms = ~40 FPS
}
```

## Process Management

Each command runs as a subprocess managed by the `ManagesProcess` trait.

### Spawning

When a command starts:

1. A new process is spawned with `proc_open()`
2. If GNU Screen is available, the command is wrapped for proper PTY handling
3. Non-blocking I/O is configured for output collection

### Output Collection

On each tick, commands collect incremental output:

```php
public function collectIncrementalOutput(): void
{
    // Read available output without blocking
    $output = stream_get_contents($this->stdout);

    if ($output !== '' && $output !== false) {
        $this->addOutput($output);
    }
}
```

### Stopping

When stopping a command:

1. `SIGTERM` is sent for graceful shutdown
2. After a timeout (5 seconds), `SIGKILL` is sent
3. Child processes are tracked and terminated

## Screen Buffer

Each command has a virtual terminal buffer powered by `soloterm/screen`:

```php
use SoloTerm\Screen\Screen;

$this->screen = new Screen(
    width: $this->scrollPaneWidth(),
    height: $this->scrollPaneHeight()
);

// Write output to the buffer
$this->screen->write($text);

// Get rendered output
$output = $this->screen->output();
```

The Screen package:

- Interprets ANSI escape sequences
- Handles cursor movement
- Manages scrollback buffer
- Supports wide characters (emoji, CJK)

## Differential Rendering

Solo optimizes terminal I/O with differential rendering:

1. **Sequence tracking** - Each write increments a sequence number
2. **Change detection** - Compare current and last-rendered sequence
3. **Selective output** - Only render lines that changed

```php
public function hasNewOutput(): bool
{
    return $this->screen->getSeqNo() !== $this->lastRenderedSeqNo;
}
```

This reduces terminal I/O by ~99.5% for typical workloads.

## GNU Screen Wrapper

Solo wraps commands in GNU Screen for:

- **PTY allocation** - Proper terminal emulation
- **ANSI rendering** - Better color and formatting support
- **Size handling** - Correct terminal dimensions

The wrapper command:

```bash
screen -U -q sh -c "your-command"
```

Disable if Screen isn't available:

```env
SOLO_USE_SCREEN=false
```

## Key Components

### Dashboard

`src/Prompt/Dashboard.php` - Main TUI controller:

- Extends Laravel Prompts
- Manages command tabs
- Handles keyboard input
- Coordinates rendering

### Manager

`src/Manager.php` - Configuration singleton:

- Loads commands from config
- Manages themes and keybindings
- Provides global access to settings

### Renderer

`src/Prompt/Renderer.php` - Frame generation:

- Builds tab bar
- Renders command output
- Displays hotkey bar
- Handles borders and layout

### Command

`src/Commands/Command.php` - Base command class:

- Process lifecycle management
- Output buffering
- Scroll state
- Hotkey handling

### ProcessTracker

`src/Support/ProcessTracker.php` - Child process management:

- Discovers child processes
- Ensures clean termination
- Prevents orphaned processes

## Data Flow

```
Subprocess → stdout → Command::collectIncrementalOutput()
                           │
                           ▼
                    Screen::write()
                           │
                           ▼
                    Screen buffer (virtual terminal)
                           │
                           ▼
                    Screen::output()
                           │
                           ▼
                    Renderer → Terminal
```

## Dependencies

Solo builds on these packages:

| Package | Purpose |
|---------|---------|
| `soloterm/screen` | Virtual terminal buffer |
| `soloterm/grapheme` | Unicode width calculation |
| `soloterm/dumps` | Dump server integration |
| `joetannenbaum/chewie` | TUI loop primitives |
| `laravel/prompts` | Base prompt system |

## Extension Points

### Custom Commands

Extend `Command` for custom behavior:

```php
class MyCommand extends Command
{
    public function boot(): void { }
    public function hotkeys(): array { }
    protected function modifyWrappedLines($lines) { }
}
```

### Custom Themes

Implement `Theme` for custom appearance:

```php
class MyTheme implements Theme
{
    public function tabs($active, $running): string { }
    public function border(): string { }
    // ...
}
```

### Custom Keybindings

Implement `HotkeyProvider` for custom keys:

```php
class MyHotkeys implements HotkeyProvider
{
    public static function keys(): array { }
    public static function keymap(): array { }
}
```

## Performance Considerations

- **40 FPS render loop** - Smooth updates without excessive CPU
- **Differential rendering** - Minimal terminal I/O
- **Non-blocking I/O** - Subprocess output collected incrementally
- **Screen buffer** - Efficient ANSI parsing and rendering

## Next Steps

- [Commands](commands) - Create custom commands
- [Themes](themes) - Customize appearance
- [Keybindings](keybindings) - Customize controls
