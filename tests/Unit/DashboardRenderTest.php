<?php

namespace SoloTerm\Solo\Tests\Unit;

use Laravel\Prompts\Output\BufferedConsoleOutput;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;
use SoloTerm\Screen\Screen;
use SoloTerm\Solo\Facades\Solo;
use SoloTerm\Solo\Prompt\Dashboard;
use SoloTerm\Solo\Prompt\Renderer;
use SoloTerm\Solo\Support\DiffRenderer;

class DashboardRenderTest extends Base
{
    #[Test]
    public function dashboard_uses_diff_rendering_without_stringifying_the_renderer(): void
    {
        $output = new BufferedConsoleOutput;
        Dashboard::setOutput($output);
        Solo::setRenderer(DiffAwareRenderer::class);

        $dashboard = new DashboardHarness;
        $dashboard->height = 8;
        $dashboard->setDiffRendererForTest(new StubDiffRenderer(40, 8, 'DIFF'));

        $dashboard->renderForTest();

        $this->assertSame('DIFF', $output->content());
    }

    #[Test]
    public function dashboard_still_supports_string_renderers_as_a_fallback(): void
    {
        $output = new BufferedConsoleOutput;
        Dashboard::setOutput($output);
        Solo::setRenderer(StringRenderer::class);

        $dashboard = new DashboardHarness;
        $dashboard->height = 4;

        $dashboard->renderForTest();

        $this->assertStringContainsString('FULL FRAME', $output->content());
    }
}

class DashboardHarness extends Dashboard
{
    public function __construct() {}

    public function renderForTest(): void
    {
        $this->render();
    }

    public function setDiffRendererForTest(?DiffRenderer $diffRenderer): void
    {
        $property = new ReflectionProperty(Dashboard::class, 'diffRenderer');
        $property->setValue($this, $diffRenderer);
    }
}

class DiffAwareRenderer extends Renderer
{
    public function __invoke(Dashboard $dashboard): static
    {
        return $this;
    }

    public function getScreen(): ?Screen
    {
        $screen = new Screen(40, 8);
        $screen->write('Diff frame');

        return $screen;
    }

    public function __toString(): string
    {
        throw new RuntimeException('The diff path should not stringify the renderer.');
    }
}

class StringRenderer extends Renderer
{
    public function __invoke(Dashboard $dashboard): string
    {
        return 'FULL FRAME';
    }
}

class StubDiffRenderer extends DiffRenderer
{
    public function __construct(int $width, int $height, protected string $renderedOutput)
    {
        parent::__construct($width, $height);
    }

    public function render(Screen $screen): string
    {
        return $this->renderedOutput;
    }
}
