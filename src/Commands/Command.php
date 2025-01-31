<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Commands;

use Chewie\Concerns\Ticks;
use Chewie\Contracts\Loopable;
use Chewie\Input\KeyPressListener;
use Illuminate\Support\Collection;
use SoloTerm\Solo\Commands\Concerns\ManagesProcess;
use SoloTerm\Solo\Hotkeys\Hotkey;
use SoloTerm\Solo\Hotkeys\KeyHandler;
use SoloTerm\Solo\Support\AnsiAware;
use SoloTerm\Solo\Support\Screen;
use SplQueue;

class Command implements Loopable
{
    use ManagesProcess, Ticks;

    public const MODE_PASSIVE = 1;

    public const MODE_INTERACTIVE = 2;

    public int $mode = Command::MODE_PASSIVE;

    public bool $focused = false;

    public bool $paused = false;

    public bool $interactive = false;

    public int $scrollIndex = 0;

    public SplQueue $lines;

    public int $height = 0;

    public int $width = 0;

    public Screen $screen;

    public ?KeyPressListener $keyPressListener = null;

    public static function from(string $command): static
    {
        return new static(command: $command);
    }

    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    public function __construct(
        public ?string $name = null,
        public ?string $command = null,
        public bool $autostart = true,
    ) {
        $this->boot();
    }

    public function boot(): void
    {
        //
    }

    public function allHotkeys(): array
    {
        // In interactive mode, the only hotkey that works is
        // Ctrl+X, to exit interactive mode. Everything else
        // gets proxied to the underlying process.
        if ($this->isInteractive()) {
            return [
                Hotkey::make("\x18", fn() => null)->label('Exit interactive mode')
            ];
        }

        $hotkeys = $this->hotkeys();

        if ($this->canBeInteractive()) {
            $hotkeys['interactive'] = Hotkey::make('i', KeyHandler::Interactive)->label('Enter interactive mode');
        }

        return array_filter($hotkeys);
    }

    /**
     * @return array<string, Hotkey>
     */
    public function hotkeys(): array
    {
        return [
            //
        ];
    }

    public function setDimensions($width, $height): static
    {
        $this->width = $width;
        $this->height = $height;

        $this->screen = $this->makeNewScreen();

        return $this;
    }

    public function lazy(): static
    {
        $this->autostart = false;

        return $this;
    }

    public function interactive(): static
    {
        $this->interactive = true;

        return $this;
    }

    public function onTick(): void
    {
        $this->collectIncrementalOutput();

        $this->marshalProcess();
    }

    public function isFocused(): bool
    {
        return $this->focused;
    }

    public function isBlurred(): bool
    {
        return !$this->isFocused();
    }

    public function canBeInteractive(): bool
    {
        return $this->interactive;
    }

    public function isInteractive(): bool
    {
        return $this->mode === self::MODE_INTERACTIVE;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    public function dd()
    {
        $this->wrappedLines()->dd();
        exit();
    }

    public function addOutput($text)
    {
        $text = str_replace('[screen is terminating]', '', $text);

        file_put_contents('debug.txt', $text, FILE_APPEND);

        $this->screen->write($text);
    }

    public function addLine($line)
    {
        $this->screen->writeln($line);
    }

    public function setMode(int $mode): bool
    {
        if (!$this->interactive) {
            $mode = static::MODE_PASSIVE;
        }

        if ($this->mode === $mode) {
            return false;
        }

        $this->mode = $mode;

        return true;
    }

    public function focus(): void
    {
        $this->focused = true;
    }

    public function blur(): void
    {
        $this->focused = false;
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function follow(): void
    {
        $this->paused = false;
    }

    public function clear(): void
    {
        $this->screen = $this->makeNewScreen();
    }

    public function catchUpScroll(): void
    {
        if (!$this->paused) {
            $this->scrollDown(INF);
            // `scrollDown` pauses, so turn follow back on.
            $this->follow();
        }
    }

    public function scrollTo($index): void
    {
        $this->scrollIndex = max(0, min(
            $index,
            $this->wrappedLines()->count() - $this->scrollPaneHeight()
        ));
    }

    public function scrollDown($amount = 1): void
    {
        $this->paused = true;
        $this->scrollTo($this->scrollIndex + $amount);
    }

    public function pageDown()
    {
        $this->scrollDown($this->scrollPaneHeight() - 1);
    }

    public function scrollUp($amount = 1): void
    {
        $this->paused = true;
        $this->scrollTo($this->scrollIndex - $amount);
    }

    public function pageUp()
    {
        $this->scrollUp($this->scrollPaneHeight() - 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Log management
    |--------------------------------------------------------------------------
    */
    public function scrollPaneHeight(): int
    {
        // Local hotkeys
        $hotkeys = (count($this->allHotkeys()) || $this->canBeInteractive()) ? 1 : 0;

        // 5 = 1 tabs + 1 process + 1 top border + 1 bottom border + 1 global hotkeys
        return $this->height - 5 - $hotkeys;
    }

    public function scrollPaneWidth(): int
    {
        // 2 box borders + 2 spaces for padding.
        return $this->width - 4;
    }

    public function wrappedLines(): Collection
    {
        $lines = explode(PHP_EOL, $this->screen->output());

        return $this->modifyWrappedLines(collect($lines))->values();
    }

    protected function makeNewScreen()
    {
        $screen = new Screen(
            width: $this->scrollPaneWidth(),
            height: $this->scrollPaneHeight()
        );

        return $screen->respondToQueriesVia(function ($output) {
            $this->input->write($output);
        });
    }

    public function wrapLine($line, $width = null, $continuationIndent = 0): array
    {
        $defaultWidth = $this->scrollPaneWidth();

        if (is_int($width)) {
            $width = $width < 0 ? $defaultWidth + $width : $width;
        }

        if (!$width) {
            $width = $defaultWidth;
        }

        $exploded = explode(PHP_EOL, AnsiAware::wordwrap(
            string: $line,
            width: $width,
            cut: true
        ));

        if ($continuationIndent === 0 || count($exploded) === 1) {
            return $exploded;
        }

        $first = array_shift($exploded);
        $indent = str_repeat(' ', $continuationIndent);

        if ($continuationIndent) {
            $allIndented = true;
            foreach ($exploded as $continuationLine) {
                $allIndented = $allIndented && str_starts_with($continuationLine, $indent);
            }

            if ($allIndented) {
                return [$first, ...$exploded];
            }
        }

        $rest = $indent . implode(PHP_EOL, $exploded);

        return [
            $first,
            ...$this->wrapLine($rest, $width, $continuationIndent)
        ];
    }

    protected function modifyWrappedLines(Collection $lines): Collection
    {
        // Primarily here for any subclasses.
        return $lines;
    }
}
