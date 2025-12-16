---
title: Themes
description: Customize Solo's appearance with themes.
---

# Themes

Solo ships with light and dark themes. You can switch between them or create your own.

## Switching Themes

Set the theme in your config or environment:

```php
// config/solo.php
'theme' => 'dark',  // or 'light'
```

Or via environment variable:

```env
SOLO_THEME=light
```

## Built-in Themes

### Dark Theme

The default theme with a dark background, optimized for most terminal color schemes.

### Light Theme

A light background theme for terminals with light color schemes.

## Creating a Custom Theme

Create a theme class that implements the `Theme` contract:

```php
<?php

namespace App\Solo\Themes;

use SoloTerm\Solo\Contracts\Theme;

class CustomTheme implements Theme
{
    public function tabs(bool $active, bool $running): string
    {
        if ($active) {
            return 'bg-blue-500 text-white font-bold';
        }

        if (!$running) {
            return 'text-gray-500';
        }

        return 'text-gray-300';
    }

    public function header(): string
    {
        return 'bg-gray-900';
    }

    public function border(): string
    {
        return 'border-gray-700';
    }

    public function footer(): string
    {
        return 'bg-gray-800 text-gray-400';
    }

    public function hotkey(): string
    {
        return 'text-cyan-400';
    }

    public function hotkeyLabel(): string
    {
        return 'text-gray-400';
    }

    public function status(): string
    {
        return 'text-gray-500';
    }

    public function content(): string
    {
        return 'bg-gray-900 text-gray-100';
    }
}
```

Register your theme:

```php
// config/solo.php
'theme' => 'custom',

'themes' => [
    'light' => Themes\LightTheme::class,
    'dark' => Themes\DarkTheme::class,
    'custom' => \App\Solo\Themes\CustomTheme::class,
],
```

## Theme Methods

Each method returns CSS-like class strings used by Laravel Prompts:

| Method | Purpose |
|--------|---------|
| `tabs($active, $running)` | Tab styling based on state |
| `header()` | Top header bar |
| `border()` | Box borders |
| `footer()` | Bottom hotkey bar |
| `hotkey()` | Hotkey characters (e.g., "c", "q") |
| `hotkeyLabel()` | Hotkey descriptions (e.g., "Clear", "Quit") |
| `status()` | Status indicators |
| `content()` | Main content area |

## Extending Built-in Themes

The easiest way to create a custom theme is to extend an existing one:

```php
<?php

namespace App\Solo\Themes;

use SoloTerm\Solo\Themes\DarkTheme;

class CustomTheme extends DarkTheme
{
    public function tabs(bool $active, bool $running): string
    {
        if ($active) {
            return 'bg-purple-600 text-white font-bold';
        }

        return parent::tabs($active, $running);
    }
}
```

## Color Classes

Solo uses Laravel Prompts styling, which supports:

- **Text colors**: `text-red-500`, `text-blue-400`, etc.
- **Background colors**: `bg-gray-900`, `bg-blue-500`, etc.
- **Font weight**: `font-bold`
- **Other styles**: `italic`, `underline`

The number suffix (100-900) indicates color intensity.

## Terminal Compatibility

Theme colors depend on your terminal's color support:

- **256-color terminals**: Full color range
- **16-color terminals**: Basic colors only
- **True color terminals**: Best color accuracy

Most modern terminals (iTerm2, Kitty, WezTerm, Ghostty) support true color.

## Next Steps

- [Keybindings](keybindings) - Customize keyboard controls
- [Configuration](configuration) - All configuration options
