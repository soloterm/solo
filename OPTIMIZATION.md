# Solo Rendering Optimizations

This document summarizes the rendering optimizations implemented in Solo to reduce terminal flicker and improve performance.

## Overview

Solo now uses differential rendering to only update cells that changed between frames, rather than rewriting the entire screen. This reduces terminal I/O by up to 99.5% in typical usage.

## Components

### DiffRenderer (`src/Support/DiffRenderer.php`)

Manages differential rendering between frames using the Screen package's CellBuffer.

**Features:**
- Maintains terminal state using CellBuffer with double-buffering
- Compares frames cell-by-cell for value-based diffing
- Uses optimized cursor movement and style tracking
- Automatically handles terminal resize (invalidates state)

**Key Methods:**
- `render(Screen $screen)` - Render a screen, returning only the diff
- `setDimensions(int $width, int $height)` - Update dimensions on resize
- `invalidate()` - Force full redraw on next render

**Benchmark:** 99.5% byte savings in frame updates

### Integration Points

**Renderer (`src/Prompt/Renderer.php`):**
- Added `$screen` property to store the Screen instance
- Added `getScreen()` method to expose Screen for differential rendering

**Dashboard (`src/Prompt/Dashboard.php`):**
- Added `$diffRenderer` property initialized with terminal dimensions
- Updated `handleResize()` to update DiffRenderer dimensions
- Updated `render()` to use differential rendering with fallback

## How It Works

1. **Frame Generation:** The Renderer generates each frame using a Screen instance, as before.

2. **Screen Extraction:** The Dashboard extracts the Screen via `getScreen()`.

3. **Differential Comparison:** DiffRenderer converts the Screen to a CellBuffer and compares against the previous frame's terminal state.

4. **Optimized Output:** Only changed cells are output using:
   - Optimized cursor movement (relative vs absolute)
   - Minimal style transitions (only changed attributes)

5. **State Update:** The terminal state buffer is swapped for the next frame comparison.

## Fallback Behavior

If the Screen cannot be accessed (e.g., custom Renderer without `getScreen()`), the Dashboard falls back to string-based comparison (original behavior).

## Requirements

Requires `soloterm/screen` package with optimization features:
- `CellBuffer` with double-buffering
- `CursorOptimizer` and `StyleTracker`
- `Screen::toCellBuffer()` method

## Local Development

To develop against a local copy of the Screen package:

```bash
php artisan solo:local
```

This adds a path repository pointing to `../screen` and updates the dependency to `@dev`.

To revert to the published package:

```bash
php artisan solo:local --revert
```

## Test Coverage

- `tests/Unit/DiffRendererTest.php` - 6 tests covering differential rendering

Run tests:
```bash
./vendor/bin/phpunit tests/Unit/DiffRendererTest.php
```

Run benchmark:
```bash
./vendor/bin/phpunit --filter "benchmark_diff_vs_full"
```
