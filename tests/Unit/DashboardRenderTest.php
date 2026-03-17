<?php

namespace SoloTerm\Solo\Tests\Unit;

use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
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

    #[Test]
    public function dashboard_reuses_the_same_renderer_instance_across_frames(): void
    {
        CachedRenderer::resetStats();

        Dashboard::setOutput(new BufferedConsoleOutput);
        Solo::setRenderer(CachedRenderer::class);

        $dashboard = new DashboardHarness;
        $dashboard->height = 4;

        $dashboard->renderForTest();
        $dashboard->renderForTest();

        $this->assertSame(1, CachedRenderer::$instances);
        $this->assertSame(2, CachedRenderer::$invocations);
    }

    #[Test]
    public function dashboard_run_does_not_crash_in_non_interactive_mode_when_required_is_unset(): void
    {
        $this->expectNotToPerformAssertions();

        Dashboard::setOutput(new BufferedConsoleOutput);
        Dashboard::interactive(false);

        try {
            $dashboard = new DashboardHarness;
            $dashboard->run();
        } finally {
            Dashboard::interactive(true);
        }
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

class CachedRenderer extends Renderer
{
    public static int $instances = 0;

    public static int $invocations = 0;

    public function __construct(Prompt $prompt)
    {
        parent::__construct($prompt);

        self::$instances++;
    }

    public static function resetStats(): void
    {
        self::$instances = 0;
        self::$invocations = 0;
    }

    public function __invoke(Dashboard $dashboard): string
    {
        self::$invocations++;

        return 'FULL FRAME';
    }
}
