<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SoloTerm\Screen\Screen;
use SoloTerm\Solo\Support\DiffRenderer;

class DiffRendererTest extends TestCase
{
    #[Test]
    public function first_frame_renders_full_output(): void
    {
        $diffRenderer = new DiffRenderer(80, 24);
        $screen = new Screen(80, 24);
        $screen->write("Hello World");

        $output = $diffRenderer->render($screen);

        // First frame should contain full output with cursor home
        $this->assertStringContainsString("\e[H", $output);
        $this->assertStringContainsString("Hello World", $output);
    }

    #[Test]
    public function identical_frames_produce_empty_output(): void
    {
        $diffRenderer = new DiffRenderer(80, 24);

        // First frame
        $screen1 = new Screen(80, 24);
        $screen1->write("Hello World");
        $diffRenderer->render($screen1);

        // Second identical frame
        $screen2 = new Screen(80, 24);
        $screen2->write("Hello World");
        $output = $diffRenderer->render($screen2);

        // Should produce no output since frames are identical
        $this->assertEquals('', $output);
    }

    #[Test]
    public function changed_frames_produce_diff_output(): void
    {
        $diffRenderer = new DiffRenderer(80, 24);

        // First frame - fill the whole screen
        $screen1 = new Screen(80, 24);
        for ($row = 0; $row < 24; $row++) {
            $screen1->write("\e[" . ($row + 1) . ";1H");
            $screen1->write(str_repeat('X', 80));
        }
        $diffRenderer->render($screen1);

        // Second frame with a few changes
        $screen2 = new Screen(80, 24);
        for ($row = 0; $row < 24; $row++) {
            $screen2->write("\e[" . ($row + 1) . ";1H");
            $line = str_repeat('X', 80);
            if ($row === 5) {
                $line = 'CHANGED!' . substr($line, 8);
            }
            $screen2->write($line);
        }
        $output = $diffRenderer->render($screen2);

        // Should contain cursor positioning and the changed characters
        $this->assertNotEmpty($output);
        // The diff should be much smaller than full redraw
        $this->assertLessThan(strlen($screen2->output()), strlen($output));
    }

    #[Test]
    public function resize_invalidates_state(): void
    {
        $diffRenderer = new DiffRenderer(80, 24);

        // First frame
        $screen1 = new Screen(80, 24);
        $screen1->write("Hello");
        $diffRenderer->render($screen1);

        $this->assertTrue($diffRenderer->hasState());

        // Resize
        $diffRenderer->setDimensions(100, 30);

        $this->assertFalse($diffRenderer->hasState());
    }

    #[Test]
    public function invalidate_clears_state(): void
    {
        $diffRenderer = new DiffRenderer(80, 24);

        // First frame
        $screen = new Screen(80, 24);
        $screen->write("Hello");
        $diffRenderer->render($screen);

        $this->assertTrue($diffRenderer->hasState());

        $diffRenderer->invalidate();

        $this->assertFalse($diffRenderer->hasState());
    }

    #[Test]
    public function benchmark_diff_vs_full(): void
    {
        $width = 120;
        $height = 40;
        $iterations = 50;

        $diffRenderer = new DiffRenderer($width, $height);

        // First frame - full render
        $screen1 = new Screen($width, $height);
        for ($row = 0; $row < $height; $row++) {
            $screen1->write("\e[" . ($row + 1) . ";1H");
            $screen1->write(str_repeat('X', $width));
        }
        $diffRenderer->render($screen1);

        // Subsequent frames - only change a few cells
        $diffBytes = 0;
        $fullBytes = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $screen = new Screen($width, $height);
            for ($row = 0; $row < $height; $row++) {
                $screen->write("\e[" . ($row + 1) . ";1H");
                // Change a few cells each frame
                $line = str_repeat('X', $width);
                if ($row === ($i % $height)) {
                    $line = 'CHANGED' . substr($line, 7);
                }
                $screen->write($line);
            }

            $diffOutput = $diffRenderer->render($screen);
            $diffBytes += strlen($diffOutput);
            $fullBytes += strlen($screen->output());
        }

        echo "\n\nDifferential Rendering Benchmark ({$iterations} frames, {$width}x{$height}):\n";
        echo "  Diff output:  {$diffBytes} bytes total\n";
        echo "  Full output:  {$fullBytes} bytes total\n";
        $savings = round((1 - $diffBytes / $fullBytes) * 100, 1);
        echo "  Byte savings: {$savings}%\n";

        // Diff should use significantly less bytes
        $this->assertLessThan($fullBytes, $diffBytes);
    }

    #[Test]
    public function benchmark_cell_comparison_overhead(): void
    {
        $width = 120;
        $height = 40;
        $iterations = 100;

        $diffRenderer = new DiffRenderer($width, $height);

        // First frame - full content
        $screen1 = new Screen($width, $height);
        for ($row = 0; $row < $height; $row++) {
            $screen1->write("\e[" . ($row + 1) . ";1H");
            $screen1->write(str_repeat('A', $width));
        }
        $diffRenderer->render($screen1);

        // Measure time for frames with only 1 cell changed (best case)
        $start = microtime(true);
        $singleCellBytes = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $screen = new Screen($width, $height);
            for ($row = 0; $row < $height; $row++) {
                $screen->write("\e[" . ($row + 1) . ";1H");
                // Only change cell at row 5, col 5
                if ($row === 5) {
                    $screen->write(str_repeat('A', 5) . 'B' . str_repeat('A', $width - 6));
                } else {
                    $screen->write(str_repeat('A', $width));
                }
            }
            $output = $diffRenderer->render($screen);
            $singleCellBytes += strlen($output);
        }

        $singleCellTime = (microtime(true) - $start) * 1000;

        // Reset for comparison
        $diffRenderer = new DiffRenderer($width, $height);
        $diffRenderer->render($screen1);

        // Measure time for frames with ALL cells changed (worst case)
        $start = microtime(true);
        $allCellsBytes = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $screen = new Screen($width, $height);
            for ($row = 0; $row < $height; $row++) {
                $screen->write("\e[" . ($row + 1) . ";1H");
                // Change every cell
                $screen->write(str_repeat('B', $width));
            }
            $output = $diffRenderer->render($screen);
            $allCellsBytes += strlen($output);
        }

        $allCellsTime = (microtime(true) - $start) * 1000;

        echo "\n\nCell Comparison Benchmark ({$iterations} iterations, {$width}x{$height}):\n";
        echo "  1 cell changed:   " . round($singleCellTime, 2) . " ms, " . $singleCellBytes . " bytes\n";
        echo "  All cells changed: " . round($allCellsTime, 2) . " ms, " . $allCellsBytes . " bytes\n";
        echo "  Output ratio:     " . round($allCellsBytes / max($singleCellBytes, 1), 1) . "x more bytes\n";

        // Output should be proportional to changes - 1 cell should output much less
        $this->assertLessThan($allCellsBytes, $singleCellBytes);
    }
}
