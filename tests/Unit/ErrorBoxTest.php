<?php

namespace SoloTerm\Solo\Tests\Unit;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Support\ErrorBox;

class ErrorBoxTest extends TestCase
{
    #[Test]
    public function it_renders_a_box_with_the_default_error_title_and_color(): void
    {
        $message = 'An error occurred';
        $errorBox = new ErrorBox($message);

        $output = $errorBox->render();

        $this->assertStringContainsString('╔', $output);
        $this->assertStringContainsString('╗', $output);
        $this->assertStringContainsString('╚', $output);
        $this->assertStringContainsString('╝', $output);
        $this->assertStringContainsString('║', $output);
        $this->assertStringContainsString('Error', $output);
        $this->assertStringContainsString($message, $output);

        $lines = explode(PHP_EOL, trim($output));
        $this->assertCount(3, $lines);
    }

    #[Test]
    public function it_renders_a_box_with_custom_title_and_multiple_message_lines(): void
    {
        $messages = ['Line 1', 'Line 2'];
        $title = 'Custom Error';
        $errorBox = new ErrorBox($messages, $title, 'red');

        $output = $errorBox->render();

        $this->assertStringContainsString($title, $output);
        $this->assertStringContainsString('Line 1', $output);
        $this->assertStringContainsString('Line 2', $output);

        $lines = explode(PHP_EOL, trim($output));
        $this->assertCount(4, $lines);
    }

    #[Test]
    public function it_applies_the_default_red_color(): void
    {
        $message = 'Color test';
        $errorBox = new ErrorBox($message);

        $output = $errorBox->render();

        $this->assertStringContainsString("\033[31m", $output);
    }

    #[Test]
    public function it_applies_a_custom_color()
    {
        $message = 'Color test';
        $errorBox = new ErrorBox($message, 'Custom Error', 'green');

        $output = $errorBox->render();

        $this->assertStringContainsString("\033[32m", $output);
    }
}
