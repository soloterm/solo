<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Prompt;

use Carbon\{Carbon, CarbonImmutable};
use Chewie\Concerns\CreatesAnAltScreen;
use Chewie\Concerns\Loops;
use Chewie\Concerns\SetsUpAndResets;
use Illuminate\Support\Collection;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Events\Event;
use SoloTerm\Solo\Facades\Solo;
use SoloTerm\Solo\Hotkeys\Hotkey;
use SoloTerm\Solo\Popups\Popup;
use SoloTerm\Solo\Popups\Quitting;
use SoloTerm\Solo\Support\Frames;
use SoloTerm\Solo\Support\KeyPressListener;
use SoloTerm\Solo\Support\Screen;

class Dashboard extends Prompt
{
    use CreatesAnAltScreen, Loops, SetsUpAndResets;

    /**
     * @var array<Command>
     */
    public array $commands = [];

    public int $selectedCommand = 0;

    public ?int $lastSelectedCommand = null;

    public int $width;

    public int $height;

    public Frames $frames;

    public KeyPressListener $listener;

    public ?Popup $popup = null;

    protected ?Carbon $lastInput = null;

    public static function start(): void
    {
        (new static)->run();
    }

    public function __construct()
    {
        $this->createAltScreen();
        $this->listenForSignals();
        $this->listenForEvents();

        $this->listener = KeyPressListener::for($this);

        [$this->width, $this->height] = $this->getDimensions();

        $this->frames = new Frames;

        $this->commands = collect(Solo::commands())
            ->tap(function (Collection $commands) {
                // If they haven't added any commands, just show the About command.
                if ($commands->isEmpty()) {
                    $commands->push(Command::make('About', 'php artisan solo:about'));
                }
            })
            ->each(function (Command $command) {
                $command->setDimensions($this->width, $this->height);
                $command->autostart();
            })
            ->all();

        $this->registerLoopables(...$this->commands);

        $this->lastInput = now();
    }

    public function listenForEvents()
    {
        Solo::on(Event::ActivateTab, function (string $name) {
            foreach (Solo::commands() as $i => $command) {
                if ($command->name === $name) {
                    $this->selectTab($i);
                    break;
                }
            }
        });
    }

    public function listenForSignals()
    {
        pcntl_signal(SIGWINCH, [$this, 'handleResize']);

        pcntl_signal(SIGINT, [$this, 'quit']);
        pcntl_signal(SIGTERM, [$this, 'quit']);
        pcntl_signal(SIGHUP, [$this, 'quit']);
        pcntl_signal(SIGQUIT, [$this, 'quit']);
    }

    public function showPopup(Popup $popup)
    {
        $this->popup = $popup;
    }

    public function exitPopup()
    {
        $this->popup = null;
    }

    public function run(): void
    {
        $this->setup($this->showDashboard(...));
    }

    public function currentCommand(): Command
    {
        return $this->commands[$this->selectedCommand];
    }

    public function getDimensions(): array
    {
        return [
            $this->terminal()->cols(),
            $this->terminal()->lines()
        ];
    }

    public function handleResize(): false
    {
        // Clear out the ENV, otherwise it just returns cached values.
        putenv('COLUMNS');
        putenv('LINES');

        $terminal = new Terminal;
        $terminal->initDimensions();

        // Put them back in, in case anyone else needs them.
        putenv('COLUMNS=' . $terminal->cols());
        putenv('LINES=' . $terminal->lines());

        [$width, $height] = $this->getDimensions();

        if ($width !== $this->width || $height !== $this->height) {
            $this->width = $width;
            $this->height = $height;

            collect($this->commands)->each->setDimensions($this->width, $this->height);
        }

        return false;
    }

    public function rebindHotkeys()
    {
        $this->listener->clear();

        collect(Solo::hotkeys())
            ->merge($this->currentCommand()->allHotkeys())
            ->each(function (Hotkey $hotkey) {
                $hotkey->init($this->currentCommand(), $this);
                $this->listener->on($hotkey->keys, $hotkey->handle(...));
            });
        $this->listener->wildcard(fn() => ($this->lastInput = now()));
    }

    public function enterInteractiveMode()
    {
        if ($this->currentCommand()->processStopped()) {
            $this->currentCommand()->restart();
        }

        $this->currentCommand()->setMode(Command::MODE_INTERACTIVE);
    }

    public function exitInteractiveMode()
    {
        $this->currentCommand()->setMode(Command::MODE_PASSIVE);
    }

    public function selectTab(int $index)
    {
        $this->lastInput = now();
        $this->currentCommand()->blur();
        $this->selectedCommand = $index;
        $this->currentCommand()->focus();
    }

    public function nextTab()
    {
        $this->selectTab(
            ($this->selectedCommand + 1) % count($this->commands)
        );
    }

    public function previousTab()
    {
        $this->selectTab(
            ($this->selectedCommand - 1 + count($this->commands)) % count($this->commands)
        );
    }

    protected function showDashboard(): void
    {
        $this->currentCommand()->focus($this);

        $this->loop($this->renderSingleFrame(...), 25_000);
    }

    protected function renderSingleFrame()
    {
        if ($this->lastSelectedCommand !== $this->selectedCommand) {
            $this->lastSelectedCommand = $this->selectedCommand;
            $this->rebindHotkeys();
        }

        // if last input was 5 seconds ago, skip rendering to save cpu cycles
        // if last output was less than a second ago - render it!
        if (
            $this->lastInput?->diffInSeconds(now()) > 5 &&
            $this->currentCommand()->lastOutput?->diffInSeconds(now()) > 1
        ) {
            $this->listener->once();

            return;
        }

        $this->currentCommand()->catchUpScroll();

        if ($this->popup) {
            if ($this->popup->shouldClose()) {
                $this->exitPopup();
            } else {
                $this->popup->renderSingleFrame();
            }
        }

        $this->render();

        if ($this->popup) {
            $this->handlePopupInput();
        } else {
            $this->currentCommand()->isInteractive() ? $this->handleInteractiveInput() : $this->listener->once();
        }

        $this->frames->next();
    }

    protected function render(): void
    {
        // This is basically what the parent `render` function does, but we can make a
        // few improvements given our unique setup. In Solo, we guarantee that the
        // entire screen is going to be written with characters, including spaces
        // padded all the way to the width of the terminal. Since that's the case,
        // we can merely move the cursor up and to (1,1) and rewrite everything.
        // Since much of the screen stays the same, it just overwrite in place.
        // The good news is since we never cleared we don't get any flicker.
        $renderer = Solo::getRenderer();
        $frame = (new $renderer($this))($this);

        if ($frame !== $this->prevFrame) {
            static::writeDirectly("\e[{$this->height}F");
            $this->output()->write($frame);

            $this->prevFrame = $frame;
        }
    }

    protected function handlePopupInput()
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        // Shorten the wait time since we're expecting keystrokes.
        if (stream_select($read, $write, $except, 0, 5_000) === 1) {
            $key = fread(STDIN, 10);
            $this->popup->handleInput($key);
        }
    }

    protected function handleInteractiveInput()
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        if ($this->currentCommand()->processStopped()) {
            $this->exitInteractiveMode();

            return;
        }

        // Shorten the wait time since we're expecting keystrokes.
        if (stream_select($read, $write, $except, 0, 5_000) === 1) {
            $key = fread(STDIN, 10);

            // For max compatibility, convert newlines to carriage returns.
            if ($key === "\n") {
                $key = "\r";
            }

            // Exit interactive mode without stopping the underlying process.
            if ($key === "\x18") {
                $this->exitInteractiveMode();

                return;
            }

            $this->currentCommand()->sendInput($key);
        }
    }

    public function quit(): void
    {
        $initiated = CarbonImmutable::now();

        $quitting = (new Quitting)->setCommands($this->commands);

        foreach ($this->commands as $command) {
            /** @var Command $command */

            // This handles stubborn processes, so we all
            // we have to do is call it and wait.
            $command->stop();
        }

        // We do need to continue looping though, because the `marshalRogueProcess` runs
        // in the loop. We'll break the loop after all processes are dead or after
        // 3 seconds. If all the processes aren't dead after three seconds then
        // the monitoring process should clean it up in the background.
        $this->loop(function () use ($initiated, $quitting) {
            // Run the renderer so it doesn't look like Solo is frozen.
            $this->renderSingleFrame();

            $allDead = array_reduce($this->commands, function ($carry, Command $command) {
                return $carry && $command->processStopped();
            }, true);

            if (!$allDead && !($this->popup instanceof Quitting)) {
                $this->showPopup($quitting);
            }

            return !($allDead || $initiated->addSeconds(3)->isPast());
        }, 25_000);

        $this->terminal()->exit();
    }

    public function value(): mixed
    {
        return null;
    }
}
