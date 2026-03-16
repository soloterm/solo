<?php

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Prompt\Dashboard;
use SoloTerm\Solo\Prompt\Renderer;
use SoloTerm\Solo\Support\Frames;

class RendererTest extends Base
{
    #[Test]
    public function renderer_keeps_tabs_on_the_first_row_in_screen_mode(): void
    {
        $dashboard = new RendererDashboardHarness;
        $dashboard->width = 180;
        $dashboard->height = 16;
        $dashboard->frames = new Frames;

        $about = Command::make('About', 'php artisan solo:about')->setDimensions(180, 16);
        $about->addLine('About output');

        $logs = Command::make('Logs', 'tail -f laravel.log')->setDimensions(180, 16);

        $dashboard->commands = [$about, $logs];

        $renderer = new Renderer($dashboard);
        $renderer($dashboard);

        $firstRow = $this->rowText($renderer->getScreen()->toCellBuffer()->getRow(0));

        $this->assertStringContainsString('About', $firstRow);
        $this->assertStringContainsString('Logs', $firstRow);
    }

    #[Test]
    public function blurred_tab_text_does_not_inherit_the_focused_tab_background(): void
    {
        $dashboard = new RendererDashboardHarness;
        $dashboard->width = 180;
        $dashboard->height = 16;
        $dashboard->frames = new Frames;

        $about = Command::make('About', 'php artisan solo:about')->setDimensions(180, 16);
        $logs = Command::make('Logs', 'tail -f laravel.log')->setDimensions(180, 16);

        $dashboard->commands = [$about, $logs];
        $dashboard->selectedCommand = 0;
        $dashboard->currentCommand()->focus();

        $renderer = new Renderer($dashboard);
        $renderer($dashboard);

        $firstRow = $renderer->getScreen()->toCellBuffer()->getRow(0);
        $firstRowText = $this->rowText($firstRow);

        $aboutStart = strpos($firstRowText, 'About');
        $logsStart = strpos($firstRowText, 'Logs');

        $this->assertNotFalse($aboutStart);
        $this->assertNotFalse($logsStart);

        // Regression: non-focused tab text should not inherit focused background.
        foreach (range($logsStart, $logsStart + 3) as $index) {
            $this->assertNull($firstRow[$index]->bg, "Expected no background on blurred tab cell {$index}.");
        }
    }

    /**
     * @param  array<int, object{char: string}>  $row
     */
    protected function rowText(array $row): string
    {
        $text = '';

        foreach ($row as $cell) {
            $text .= $cell->char;
        }

        return rtrim($text);
    }
}

class RendererDashboardHarness extends Dashboard
{
    public function __construct() {}
}
