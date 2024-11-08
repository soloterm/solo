<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Prompt;

use AaronFrancis\Solo\Commands\Command;
use AaronFrancis\Solo\Facades\Solo;
use Chewie\Concerns\CreatesAnAltScreen;
use Chewie\Concerns\Loops;
use Chewie\Concerns\RegistersRenderers;
use Chewie\Concerns\SetsUpAndResets;
use Chewie\Input\KeyPressListener;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;

class Dashboard extends Prompt
{
    use CreatesAnAltScreen, Loops, RegistersRenderers, SetsUpAndResets;

    /**
     * @var array<Command>
     */
    public array $commands = [];

    public int $selectedCommand = 0;

    public int $width;

    public int $height;

    public static function start(): void
    {
        (new static)->run();
    }

    public function __construct()
    {
        $this->registerRenderer(Solo::getRenderer());
        $this->createAltScreen();

        [$this->width, $this->height] = $this->getDimensions();

        pcntl_signal(SIGWINCH, [$this, 'handleResize']);

        $this->commands = collect(Solo::commands())->each(function (Command $command) {
            $command->setDimensions($this->width, $this->height);
            $command->autostart();
        })->all();

        $this->registerLoopables(...$this->commands);
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

        // Get our buffered dimensions.
        [$width, $height] = $this->getDimensions();

        if ($width !== $this->width || $height !== $this->height) {
            $this->width = $width;
            $this->height = $height;

            collect($this->commands)->each->setDimensions($this->width, $this->height);
        }

        return false;
    }

    protected function showDashboard(): void
    {
        $listener = KeyPressListener::for($this)
            ->on('D', fn() => $this->currentCommand()->dd())
            // Logs
            ->on('c', fn() => $this->currentCommand()->clear())
            ->on('p', fn() => $this->currentCommand()->pause())
            ->on('f', fn() => $this->currentCommand()->follow())
            ->on('r', fn() => $this->currentCommand()->restart())

            // Scrolling
            ->on(['j', Key::DOWN, Key::DOWN_ARROW], fn() => $this->currentCommand()->scrollDown())
            ->on([Key::CTRL_D, Key::SHIFT_DOWN ], fn() => $this->currentCommand()->scrollDown(10))
            ->on(['k', Key::UP, Key::UP_ARROW], fn() => $this->currentCommand()->scrollUp())
            ->on([Key::CTRL_U, Key::SHIFT_UP], fn() => $this->currentCommand()->scrollUp(10))

            // Processes
            ->on('s', fn() => $this->currentCommand()->toggle())
            ->on(['h', Key::LEFT, Key::LEFT_ARROW], function () {
                $this->currentCommand()->blur();

                $this->selectedCommand = ($this->selectedCommand - 1 + count($this->commands)) % count($this->commands);

                $this->currentCommand()->focus();
            })
            ->on(['l', Key::RIGHT, Key::RIGHT_ARROW], function () {
                $this->currentCommand()->blur();

                $this->selectedCommand = ($this->selectedCommand + 1) % count($this->commands);

                $this->currentCommand()->focus();
            })

            // Quit
            ->on(['q', Key::CTRL_C], fn() => $this->quit());

        $this->currentCommand()->focus();

        $this->loop(function () use ($listener) {
            $this->currentCommand()->catchUpScroll();
            $this->render();

            $listener->once();
        }, 25_000);

        // @TODO reconsider using?
        // $this->loopWithListener($listener, function () {
        //     $this->currentCommand()->catchUpScroll();
        //     $this->render();
        // });
    }

    public function quit(): void
    {
        foreach ($this->commands as $command) {
            /** @var Command $command */

            // This handles stubborn processes, so we all
            // we have to do is call it and wait.
            $command->stop();
        }

        while (true) {
            $any = array_reduce($this->commands, function ($carry, $command) {
                return $carry || $command->processRunning();
            }, false);

            if ($any) {
                Sleep::for(100)->milliseconds();
            } else {
                break;
            }
        }

        $this->terminal()->exit();
    }

    public function loopWithListener(KeyPressListener $listener, $cb, int $frameDuration = 100_000): void
    {
        // Call immediately before we start looping.
        $cb($this);

        $lastTick = microtime(true);

        while (true) {
            $read = [STDIN];
            $write = [];
            $except = [];

            // Use stream_select to implement the sleep, but also respond immediately to key presses
            $changed = stream_select($read, $write, $except, 0, $frameDuration);

            if ($changed === false) {
                echo "An error occurred while waiting for input.\n";
                exit;
            }

            // A key was pressed, so execute this listener.
            if ($changed > 0) {
                $listener->once();
            }

            // Calculate the time elapsed since the last tick
            $currentTime = microtime(true);

            // Convert seconds to microseconds
            $elapsedMicroseconds = ($currentTime - $lastTick) * 1e6;

            // Respond to key presses immediately
            $continue = $cb($this);

            if ($continue === false) {
                break;
            }

            // Only tick if it's been greater than minSleep microseconds.
            if ($elapsedMicroseconds < $frameDuration) {
                continue;
            }

            $lastTick = $currentTime;

            foreach ($this->loopables as $component) {
                $component->tick();
            }
        }
    }

    public function value(): mixed
    {
        return null;
    }
}
