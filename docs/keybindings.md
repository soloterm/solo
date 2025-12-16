---
title: Keybindings
description: Keyboard controls and how to customize them.
---

# Keybindings

Solo is keyboard-driven. Learn the controls to work efficiently.

## Default Keybindings

### Navigation

| Key | Action |
|-----|--------|
| `←` / `→` | Switch between tabs |
| `↑` / `↓` | Scroll output up/down |
| `Shift+↑` / `Shift+↓` | Page up/down |
| `Home` | Jump to top |
| `End` | Jump to bottom |
| `g` | Open tab picker (jump to any tab) |

### Command Control

| Key | Action |
|-----|--------|
| `s` | Start/Stop current command |
| `r` | Restart current command |
| `c` | Clear output |
| `p` | Pause (stop auto-scrolling) |
| `f` | Follow (resume auto-scrolling) |

### Interactive Mode

| Key | Action |
|-----|--------|
| `i` | Enter interactive mode |
| `Ctrl+X` | Exit interactive mode |

Interactive mode forwards your keystrokes to the underlying command. Use it for commands that require input, like `php artisan tinker`.

### Global

| Key | Action |
|-----|--------|
| `q` | Quit Solo |
| `Ctrl+C` | Quit Solo |

## Vim Keybindings

Switch to Vim keybindings in your config or environment:

```php
// config/solo.php
'keybinding' => 'vim',
```

Or via environment variable:

```env
SOLO_KEYBINDING=vim
```

### Vim Navigation

| Key | Action |
|-----|--------|
| `h` / `l` | Switch between tabs (instead of arrows) |
| `j` / `k` | Scroll down/up (instead of arrows) |
| `Ctrl+D` | Page down |
| `Ctrl+U` | Page up |

All other keybindings remain the same.

## Creating Custom Keybindings

Create a custom keybinding class by implementing `HotkeyProvider`:

```php
<?php

namespace App\Solo\Hotkeys;

use Laravel\Prompts\Key;
use SoloTerm\Solo\Contracts\HotkeyProvider;
use SoloTerm\Solo\Hotkeys\DefaultHotkeys;
use SoloTerm\Solo\Hotkeys\Hotkey;
use SoloTerm\Solo\Hotkeys\KeyHandler;

class CustomHotkeys implements HotkeyProvider
{
    public static function keys(): array
    {
        return array_values(static::keymap());
    }

    public static function keymap(): array
    {
        // Start with default keys
        $map = DefaultHotkeys::keymap();

        // Modify specific keys
        $map['quit']->remap('x');  // Use 'x' instead of 'q' to quit

        return $map;
    }
}
```

Register your custom keybindings:

```php
// config/solo.php
'keybinding' => 'custom',

'keybindings' => [
    'default' => Hotkeys\DefaultHotkeys::class,
    'vim' => Hotkeys\VimHotkeys::class,
    'custom' => \App\Solo\Hotkeys\CustomHotkeys::class,
],
```

## The Hotkey Class

Hotkeys are defined using the `Hotkey` class:

```php
use SoloTerm\Solo\Hotkeys\Hotkey;
use SoloTerm\Solo\Hotkeys\KeyHandler;

// Basic hotkey
Hotkey::make('c', KeyHandler::Clear)
    ->label('Clear');

// Multiple keys for same action
Hotkey::make(['q', Key::CTRL_C], KeyHandler::Quit)
    ->label('Quit');

// Conditional visibility
Hotkey::make('p', KeyHandler::Pause)
    ->label('Pause')
    ->visible(fn(Command $command) => !$command->paused);

// Hidden from display (still functional)
Hotkey::make(Key::UP_ARROW, KeyHandler::ScrollUp)
    ->invisible();
```

## Available KeyHandlers

The `KeyHandler` enum defines available actions:

| Handler | Action |
|---------|--------|
| `KeyHandler::Clear` | Clear command output |
| `KeyHandler::Pause` | Pause auto-scrolling |
| `KeyHandler::Follow` | Resume auto-scrolling |
| `KeyHandler::StartStop` | Start or stop command |
| `KeyHandler::Restart` | Restart command |
| `KeyHandler::Quit` | Quit Solo |
| `KeyHandler::PreviousTab` | Go to previous tab |
| `KeyHandler::NextTab` | Go to next tab |
| `KeyHandler::ScrollUp` | Scroll up one line |
| `KeyHandler::ScrollDown` | Scroll down one line |
| `KeyHandler::PageUp` | Scroll up one page |
| `KeyHandler::PageDown` | Scroll down one page |
| `KeyHandler::Home` | Scroll to top |
| `KeyHandler::End` | Scroll to bottom |
| `KeyHandler::ShowTabPicker` | Open tab picker |
| `KeyHandler::Interactive` | Enter interactive mode |

## Command-Specific Hotkeys

Commands can define their own hotkeys:

```php
class MyCommand extends Command
{
    public function hotkeys(): array
    {
        return [
            'custom' => Hotkey::make('x', function() {
                // Custom action
            })->label('Custom'),
        ];
    }
}
```

These appear in the hotkey bar when the command's tab is active.

## Next Steps

- [Themes](themes) - Customize appearance
- [Commands](commands) - Configure commands
