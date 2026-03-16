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
     * Scratch CellBuffer reused for the next frame to avoid per-tick allocations.
     */
    protected ?CellBuffer $scratchState = null;

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
            $this->scratchState = null;
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
        // Reuse a scratch buffer so the diff path doesn't allocate a fresh frame buffer every tick.
        $newState = $screen->toCellBuffer($this->scratchState);

        // First frame - need full render
        if ($this->terminalState === null) {
            $this->terminalState = $newState;
            $this->scratchState = new CellBuffer($this->width, $this->height);

            // Return full output for first frame
            return "\e[H" . $screen->output();
        }

        // Compare the previous and current cell state to emit only the changed output.
        $output = $this->renderDiff($this->terminalState, $newState);

        // Swap buffers so the previous terminal state becomes next frame's scratch buffer.
        $previousState = $this->terminalState;
        $this->terminalState = $newState;
        $this->scratchState = $previousState;

        return $output;
    }

    /**
     * Force a full redraw on the next render.
     */
    public function invalidate(): void
    {
        $this->terminalState = null;
        $this->scratchState = null;
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
     * Skips unchanged rows via cached row hashes, then compares only the
     * changed rows cell-by-cell with optimized cursor movement and style
     * tracking.
     */
    protected function renderDiff(CellBuffer $oldState, CellBuffer $newState): string
    {
        $cursor = new CursorOptimizer;
        $style = new StyleTracker;
        $parts = [];

        for ($row = 0; $row < $this->height; $row++) {
            if ($oldState->rowEquals($row, $newState)) {
                continue;
            }

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
