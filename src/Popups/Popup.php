<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Popups;

use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use SoloTerm\Solo\Hotkeys\KeycodeMap;
use SoloTerm\Solo\Support\Screen;

abstract class Popup
{
    use InteractsWithStrings;

    public Screen $screen;

    public function __construct()
    {
        $this->screen = new Screen(80, 30);

        $this->boot();

        $this->bootTraits();
    }

    public function boot()
    {
        //
    }

    public function bootTraits()
    {
        $class = static::class;

        $booted = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists($class, $method) && !in_array($method, $booted)) {
                call_user_func([$this, $method]);

                $booted[] = $method;
            }
        }
    }

    abstract public function renderSingleFrame();

    abstract public function handleInput($key);

    abstract public function shouldClose(): bool;

    public function output()
    {
        return $this->screen->output();
    }

    public function render(int $offsetX = 0, int $offsetY = 0)
    {
        $output = $this->output();

        $rendered = "\e[H\e[{$offsetY}B"
            . "\e[{$offsetX}C\e[0m ┌" . str_repeat('─', 81) . '┒ '
            . PHP_EOL;

        foreach (explode(PHP_EOL, $output) as $line) {
            $rendered .= "\e[{$offsetX}C │ " . $this->pad($line, 80) . "\e[0m┃ " . PHP_EOL;
        }

        $rendered .= "\e[{$offsetX}C\e[0m ┕" . str_repeat('━', 81) . '┛ '
            . PHP_EOL
            . "\e[{$offsetX}C\e[0;2m  "
            . $this->footer();

        return $rendered;
    }

    public function footer()
    {
        return 'Press ' . KeycodeMap::toDisplay("\x18") . ' to exit without saving.';
    }
}
