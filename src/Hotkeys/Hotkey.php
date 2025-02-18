<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Hotkeys;

use Closure;
use ReflectionFunction;
use ReflectionParameter;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Prompt\Dashboard;
use SoloTerm\Solo\Support\KeyPressListener;

class Hotkey
{
    protected Dashboard $prompt;

    protected Command $command;

    protected ?Closure $visibleWhen = null;

    protected ?Closure $activeWhen = null;

    protected ?Closure $keyDisplay = null;

    protected Closure $handler;

    protected Closure|string|null $label = null;

    protected ?KeyHandler $fromKeyHandler = null;

    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    public function __construct(public array|string|null $keys = [], KeyHandler|Closure|null $handler = null)
    {
        if ($handler instanceof KeyHandler) {
            $this->fromKeyHandler = $handler;
            $handler = $handler->handler();
        }

        // A no-op, display only key.
        if (is_null($handler)) {
            $handler = fn() => null;
        }

        $this->keyDisplay = function () {
            $key = is_array($this->keys) ? $this->keys[0] : $this->keys;

            return KeycodeMap::toDisplay($key);
        };

        $this->handler = $handler;
    }

    public function init(Command $command, Dashboard $prompt): void
    {
        $this->command = $command;
        $this->prompt = $prompt;
    }

    public function handle(): void
    {
        if ($this->active()) {
            $this->callWithParams($this->handler);
        }
    }

    public function remap(array|string $keys): static
    {
        $this->keys = $keys;

        return $this;
    }

    public function label(string|Closure $value)
    {
        $this->label = $value;

        return $this;
    }

    public function makeLabel()
    {
        return $this->callWithParams($this->label);
    }

    public function keyDisplay(Closure|string|null $cb = null): string|static
    {
        if (is_null($cb)) {
            return $this->callWithParams($this->keyDisplay);
        }

        if (is_string($cb)) {
            $cb = fn() => $cb;
        }

        $this->keyDisplay = $cb;

        return $this;
    }

    public function visible(Closure|bool|null $cb = null): bool|static
    {
        if (is_bool($cb)) {
            $cb = fn() => $cb;
        }

        if (is_null($cb)) {
            return is_null($this->visibleWhen) ? true : $this->callWithParams($this->visibleWhen);
        }

        $this->visibleWhen = $cb;

        return $this;
    }

    public function invisible()
    {
        return $this->visible(false);
    }

    public function active(Closure|bool|null $cb = null): bool|static
    {
        if (is_bool($cb)) {
            $cb = fn() => $cb;
        }

        if (is_null($cb)) {
            return is_null($this->activeWhen) ? true : $this->callWithParams($this->activeWhen);
        }

        $this->activeWhen = $cb;

        return $this;
    }

    public function callWithParams(string|Closure|null $value): mixed
    {
        if (is_string($value) || is_null($value)) {
            return $value;
        }

        $reflected = new ReflectionFunction($value);

        $reflected->getParameters();
        $arguments = collect($reflected->getParameters())
            ->map(function (ReflectionParameter $parameter) {
                return match ($parameter->getType()->getName()) {
                    Command::class => $this->command,
                    Dashboard::class => $this->prompt,
                    KeyPressListener::class => $this->prompt->listener,
                    Hotkey::class => $this,
                    default => null
                };
            });

        return call_user_func($value, ...$arguments->all());
    }
}
