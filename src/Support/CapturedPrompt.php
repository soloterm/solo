<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Exception;
use Laravel\Prompts\Themes\Default as Themes;
use ReflectionClass;

trait CapturedPrompt
{
    public Screen $screen;

    protected bool $complete = false;

    public function setScreen(Screen $screen)
    {
        $this->screen = $screen;
    }

    public function isComplete()
    {
        return $this->complete;
    }

    public function prompt(): mixed
    {
        throw new Exception('Do not call `prompt` directly on a CapturedPrompt.');
    }

    public function callNativeKeyPressHandler($key)
    {
        // Key presses often cause re-renders, so we have to capture that.
        return $this->withOutputCaptured(function () use ($key) {
            return (new ReflectionClass($this))->getMethod('handleKeyPress')->invoke($this, $key);
        });
    }

    public function renderSingleFrame()
    {
        $this->withOutputCaptured($this->render(...));
    }

    protected function withOutputCaptured($cb)
    {
        $terminal = new FalseTerminal;
        $terminal->width = $this->screen->width;
        $terminal->height = $this->screen->height;

        $originalTerminal = static::$terminal;
        $originalOutput = static::$output;

        static::$terminal = $terminal;
        static::$output = new ScreenOutput($this->screen);

        try {
            $output = $cb();
        } finally {
            static::$terminal = $originalTerminal;
            static::$output = $originalOutput;
        }

        return $output;
    }

    protected function getRenderer(): callable
    {
        $renderer = match (static::class) {
            CapturedMultiSelectPrompt::class => Themes\MultiSelectPromptRenderer::class,
            CapturedTextPrompt::class => Themes\TextPromptRenderer::class,
            CapturedSearchPrompt::class => Themes\SearchPromptRenderer::class,
            CapturedSuggestPrompt::class => Themes\SuggestPromptRenderer::class,
            CapturedQuickPickPrompt::class => Themes\SearchPromptRenderer::class,

            default => throw new Exception('Unknown prompt type.'),
        };

        return new $renderer($this);
    }

    public function __destruct()
    {
        // Not needed as we're not using the real terminal.
    }
}
