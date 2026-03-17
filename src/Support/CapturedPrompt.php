<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Exception;
use Laravel\Prompts\Key;
use ReflectionClass;
use SoloTerm\Screen\Screen;

trait CapturedPrompt
{
    public Screen $screen;

    protected bool $complete = false;

    public function setScreen(Screen $screen): void
    {
        $this->screen = $screen;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function prompt(): mixed
    {
        throw new Exception('Do not call `prompt` directly on a CapturedPrompt.');
    }

    public function callNativeKeyPressHandler(string $key): mixed
    {
        // Key presses often cause re-renders, so we have to capture that.
        return $this->withOutputCaptured(function () use ($key) {
            return (new ReflectionClass($this))->getMethod('handleKeyPress')->invoke($this, $key);
        });
    }

    public function renderSingleFrame(): void
    {
        $this->withOutputCaptured($this->render(...));
    }

    protected function withOutputCaptured(callable $cb): mixed
    {
        $terminal = new FalseTerminal;
        $terminal->width = $this->screen->width;
        $terminal->height = $this->screen->height;

        $originalTerminal = static::terminal();
        $originalOutput = static::output();

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

    /**
     * @return class-string
     */
    abstract protected function rendererClass(): string;

    protected function getRenderer(): callable
    {
        $renderer = $this->rendererClass();

        return new $renderer($this);
    }

    public function __destruct()
    {
        // Not needed as we're not using the real terminal.
    }

    public function handleInput(string $key): void
    {
        $continue = $this->callNativeKeyPressHandler($key);

        if ($continue === false || $key === Key::CTRL_C) {
            $this->complete = true;
            $this->clearListeners();
        }
    }
}
