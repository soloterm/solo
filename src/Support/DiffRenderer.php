<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use SoloTerm\Grapheme\Grapheme;
use SoloTerm\Screen\Buffers\CellBuffer;
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
     * Cursor optimizer reused across frames to avoid repeated allocations.
     */
    protected CursorOptimizer $cursorOptimizer;

    /**
     * Style tracker reused across frames to avoid repeated allocations.
     */
    protected StyleTracker $styleTracker;

    /**
     * Whether to use optimized rendering (cursor/style tracking).
     */
    protected bool $optimized = true;

    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->cursorOptimizer = new CursorOptimizer;
        $this->styleTracker = new StyleTracker;
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
        $this->cursorOptimizer->reset();
        $this->styleTracker->reset();

        $output = '';
        $hasChanges = false;

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

                if (!$hasChanges) {
                    // Re-home when the first changed cell is encountered so
                    // relative cursor movement starts from a known position
                    // instead of relying on terminal cursor residue.
                    $output = "\e[H";
                    $hasChanges = true;
                }

                // Get optimized cursor movement
                $output .= $this->cursorOptimizer->moveTo($row, $col);

                // Get optimized style transition
                $output .= $this->styleTracker->transitionTo($newCell);

                // Output the character
                $output .= $newCell->char;

                // Track cursor position after character
                $this->cursorOptimizer->advance(max(0, Grapheme::wcwidth($newCell->char)));
            }
        }

        if (!$hasChanges) {
            return '';
        }

        // Reset styles at the end if needed
        $output .= $this->styleTracker->resetIfNeeded();

        return $output;
    }
}
