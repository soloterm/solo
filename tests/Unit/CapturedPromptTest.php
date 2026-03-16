<?php

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Screen\Screen;
use SoloTerm\Solo\Support\CapturedTextPrompt;
use SoloTerm\Solo\Support\ScreenOutput;

class CapturedPromptTest extends Base
{
    #[Test]
    public function screen_output_accepts_the_extracted_screen_class(): void
    {
        $screen = new Screen(40, 10);
        $output = new ScreenOutput($screen);

        $output->writeln('Hello from ScreenOutput');

        $this->assertStringContainsString('Hello from ScreenOutput', $screen->output());
    }

    #[Test]
    public function captured_prompts_accept_the_extracted_screen_class(): void
    {
        $screen = new Screen(80, 30);
        $prompt = new CapturedTextPrompt(
            label: 'Type a new command',
            placeholder: 'php artisan foo:bar',
        );

        $prompt->setScreen($screen);
        $prompt->renderSingleFrame();

        $this->assertStringContainsString('Type a new command', $screen->output());
    }
}
