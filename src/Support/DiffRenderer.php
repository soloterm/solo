<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use SoloTerm\Screen\Buffers\CellBuffer;
use SoloTerm\Screen\Cell;
use SoloTerm\Screen\Output\CursorOptimizer;
use SoloTerm\Screen\Output\StyleTracker;
use SoloTerm\Screen\Screen;

/**
 * Manages differential rendering between frames.
 *
 * Instead of rewriting the entire screen on each frame, this class
 * tracks what's currently displayed and only outputs the differences.
 * This significantly reduces terminal I/O and eliminates flicker.
 */
class DiffRenderer
{
    /**
     * CellBuffer representing what's currently on the terminal.
     */
    protected ?CellBuffer $terminalState = null;

    /**
     * Terminal dimensions.
     */
    protected int $width;

    protected int $height;

    /**
     * Whether to use optimized rendering (cursor/style tracking).
     */
    protected bool $optimized = true;

    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Update dimensions (e.g., on terminal resize).
     */
    public function setDimensions(int $width, int $height): void
    {
        if ($this->width !== $width || $this->height !== $height) {
            $this->width = $width;
            $this->height = $height;
            // Reset state on resize - need full redraw
            $this->terminalState = null;
        }
    }

    /**
     * Enable/disable optimized rendering.
     */
    public function setOptimized(bool $optimized): void
    {
        $this->optimized = $optimized;
    }

    /**
     * Render a Screen, returning only the differences from the previous frame.
     *
     * @param  Screen  $screen  The screen to render
     * @return string ANSI output to update the terminal (may be empty if no changes)
     */
    public function render(Screen $screen): string
    {
        // Convert screen to CellBuffer for cell-level comparison
        $newState = $screen->toCellBuffer();

        // First frame - need full render
        if ($this->terminalState === null) {
            $this->terminalState = $newState;

            // Return full output for first frame
            return "\e[H" . $screen->output();
        }

        // Direct comparison between old and new state - O(changed) not O(all)
        $output = $this->renderDiff($this->terminalState, $newState);

        // Replace terminal state with new state for next frame
        $this->terminalState = $newState;

        return $output;
    }

    /**
     * Force a full redraw on the next render.
     */
    public function invalidate(): void
    {
        $this->terminalState = null;
    }

    /**
     * Check if we have state (have rendered at least one frame).
     */
    public function hasState(): bool
    {
        return $this->terminalState !== null;
    }

    /**
     * Render differences between two CellBuffers.
     *
     * Uses single-pass cell-level comparison with optimized cursor
     * movement and style tracking. Direct comparison is faster than
     * hash-based row comparison because it avoids double iteration
     * and Cell::equals() can short-circuit on early differences.
     */
    protected function renderDiff(CellBuffer $oldState, CellBuffer $newState): string
    {
        $cursor = new CursorOptimizer;
        $style = new StyleTracker;
        $parts = [];

        for ($row = 0; $row < $this->height; $row++) {
            for ($col = 0; $col < $this->width; $col++) {
                $oldCell = $oldState->getCell($row, $col);
                $newCell = $newState->getCell($row, $col);

                // Skip if cell hasn't changed
                if ($oldCell->equals($newCell)) {
                    continue;
                }

                // Skip continuation cells
                if ($newCell->isContinuation()) {
                    continue;
                }

                // Get optimized cursor movement
                $parts[] = $cursor->moveTo($row, $col);

                // Get optimized style transition
                $parts[] = $style->transitionTo($newCell);

                // Output the character
                $parts[] = $newCell->char;

                // Track cursor position after character
                $cursor->advance(1);
            }
        }

        // Reset styles at the end if needed
        $parts[] = $style->resetIfNeeded();

        return implode('', $parts);
    }
}
