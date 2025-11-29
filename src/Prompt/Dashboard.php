<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Prompt;

use Carbon\CarbonImmutable;
use Chewie\Concerns\CreatesAnAltScreen;
use Chewie\Concerns\Loops;
use Chewie\Concerns\SetsUpAndResets;
use Illuminate\Support\Collection;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;
use SoloTerm\Screen\Screen;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Events\Event;
use SoloTerm\Solo\Facades\Solo;
use SoloTerm\Solo\Hotkeys\Hotkey;
use SoloTerm\Solo\Popups\Popup;
use SoloTerm\Solo\Popups\Quitting;
use SoloTerm\Solo\Support\DiffRenderer;
use SoloTerm\Solo\Support\Frames;
use SoloTerm\Solo\Support\KeyPressListener;

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

    /**
     * Differential renderer for efficient screen updates.
     */
    protected ?DiffRenderer $diffRenderer = null;

    /**
     * Last resize timestamp for debouncing (in microseconds).
     */
    protected ?float $lastResizeTime = null;

    /**
     * Minimum interval between resize operations (100ms).
     */
    protected const RESIZE_DEBOUNCE_MS = 100;

    /**
     * Minimum frame interval in microseconds (25ms = 40 FPS max).
     * Used when there's activity.
     */
    protected const MIN_FRAME_INTERVAL_US = 25_000;

    /**
     * Maximum frame interval in microseconds (100ms = 10 FPS min).
     * Used when idle to save CPU.
     */
    protected const MAX_FRAME_INTERVAL_US = 100_000;

    /**
     * Number of consecutive idle ticks before slowing down.
     */
    protected const IDLE_THRESHOLD = 8;

    /**
     * Counter for consecutive ticks without activity.
     */
    protected int $idleTicks = 0;

    /**
     * Flag indicating user input was received this frame.
     */
    protected bool $hadInputThisFrame = false;

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

        // Initialize differential renderer for efficient screen updates
        $this->diffRenderer = new DiffRenderer($this->width, $this->height);

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
        // Debounce rapid resize events (e.g., dragging window edge)
        $now = microtime(true) * 1000;
        if ($this->lastResizeTime !== null &&
            ($now - $this->lastResizeTime) < static::RESIZE_DEBOUNCE_MS) {
            return false;
        }
        $this->lastResizeTime = $now;

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

            // Update diff renderer dimensions (this also invalidates the state)
            $this->diffRenderer?->setDimensions($this->width, $this->height);
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

    public function headTab()
    {
        $this->selectTab(0);
    }

    public function tailTab()
    {
        $this->selectTab(count($this->commands) - 1);
    }

    protected function showDashboard(): void
    {
        $this->currentCommand()->focus($this);

        $this->adaptiveLoop();
    }

    /**
     * Custom loop that combines sleeping with input waiting for better responsiveness.
     * Uses adaptive frame rate based on activity.
     */
    protected function adaptiveLoop(): void
    {
        while (true) {
            // Tick all commands to collect output (before rendering)
            foreach ($this->loopables as $component) {
                $component->tick();
            }

            // Check for activity to determine frame rate
            $this->updateActivityState();

            // Render the current frame
            $this->renderSingleFrame();

            // Wait for input with adaptive timeout
            // This combines the sleep with input checking for better responsiveness
            $timeout = $this->calculateFrameTimeout();
            $this->waitForInputOrTimeout($timeout);

            $this->frames->next();
        }
    }

    /**
     * Update activity tracking based on command output and state.
     */
    protected function updateActivityState(): void
    {
        $hasActivity = false;

        // Check if any command received output or is stopping
        foreach ($this->commands as $command) {
            if ($command->hadOutputThisTick() || $command->isStopping()) {
                $hasActivity = true;
                break;
            }
        }

        // Also count user input as activity
        if ($this->hadInputThisFrame) {
            $hasActivity = true;
            $this->hadInputThisFrame = false;
        }

        // Track consecutive idle ticks
        if ($hasActivity) {
            $this->idleTicks = 0;
        } else {
            $this->idleTicks++;
        }
    }

    /**
     * Calculate the frame timeout based on activity level.
     */
    protected function calculateFrameTimeout(): int
    {
        if ($this->idleTicks < static::IDLE_THRESHOLD) {
            return static::MIN_FRAME_INTERVAL_US;
        }

        // Gradually increase timeout as idle time grows
        $factor = min($this->idleTicks - static::IDLE_THRESHOLD + 1, 4);

        return min(
            static::MIN_FRAME_INTERVAL_US * $factor,
            static::MAX_FRAME_INTERVAL_US
        );
    }

    /**
     * Wait for user input or timeout, whichever comes first.
     * This replaces the fixed usleep with an adaptive wait that
     * responds immediately to user input.
     */
    protected function waitForInputOrTimeout(int $timeoutUs): void
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        // Convert microseconds to seconds and microseconds for stream_select
        $seconds = (int) floor($timeoutUs / 1_000_000);
        $microseconds = $timeoutUs % 1_000_000;

        $result = @stream_select($read, $write, $except, $seconds, $microseconds);

        if ($result === 1) {
            // Input is available - mark activity and process it
            $this->hadInputThisFrame = true;
            $this->idleTicks = 0;

            $key = fread(STDIN, 10);

            if ($this->popup) {
                $this->popup->handleInput($key);
            } elseif ($this->currentCommand()->isInteractive()) {
                $this->processInteractiveKey($key);
            } else {
                $this->listener->processKey($key);
            }
        }
    }

    /**
     * Process a key in interactive mode.
     */
    protected function processInteractiveKey(string $key): void
    {
        if ($this->currentCommand()->processStopped()) {
            $this->exitInteractiveMode();

            return;
        }

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

    protected function renderSingleFrame()
    {
        if ($this->lastSelectedCommand !== $this->selectedCommand) {
            $this->lastSelectedCommand = $this->selectedCommand;
            $this->rebindHotkeys();
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

        // Note: Input handling is now done in waitForInputOrTimeout()
    }

    protected function render(): void
    {
        // Generate the frame using the standard renderer
        $renderer = Solo::getRenderer();
        $rendererInstance = (new $renderer($this))($this);

        // The Renderer returns itself, but __toString gives us the frame
        $frame = (string) $rendererInstance;

        // Try differential rendering if available
        if ($this->diffRenderer !== null && method_exists($rendererInstance, 'getScreen')) {
            try {
                $screen = $rendererInstance->getScreen();
                $output = $this->diffRenderer->render($screen);

                if ($output !== '') {
                    $this->output()->write($output);
                }

                return;
            } catch (\Throwable $e) {
                // Differential rendering failed, fall through to string comparison
                $this->diffRenderer = null; // Disable for future frames
            }
        }

        // Fallback to string-based comparison (original behavior)
        if ($frame !== $this->prevFrame) {
            static::writeDirectly("\e[{$this->height}F");
            $this->output()->write($frame);

            $this->prevFrame = $frame;
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
