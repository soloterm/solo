<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Commands\Concerns;

use Closure;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ReflectionClass;
use SoloTerm\Solo\Support\ErrorBox;
use SoloTerm\Solo\Support\PendingProcess;
use SoloTerm\Solo\Support\ProcessTracker;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process as SymfonyProcess;

trait ManagesProcess
{
    public ?InvokedProcess $process = null;

    protected array $afterTerminateCallbacks = [];

    protected bool $stopping = false;

    protected ?Carbon $stopInitiatedAt;

    protected ?Closure $processModifier = null;

    public InputStream $input;

    protected string $partialBuffer = '';

    protected $children = [];

    public function createPendingProcess(): PendingProcess
    {
        $this->input ??= new InputStream;

        $command = explode(' ', $this->command);

        // ??
        // alias screen='TERM=xterm-256color screen'
        // https://superuser.com/questions/800126/gnu-screen-changes-vim-syntax-highlighting-colors
        // https://github.com/derailed/k9s/issues/2810

        $screen = $this->makeNewScreen();

        // We have to make our own so that we can control pty.
        $process = app(PendingProcess::class)
            // ->command($command)
            ->command([
                'bash',
                '-c',
                "stty cols {$screen->width} rows {$screen->height} && screen -q " . $this->command,
            ])
            ->forever()
            ->timeout(0)
            ->idleTimeout(0)
            // Regardless of whether or not it's an interactive process, we're
            // still going to register an input stream. This lets command-
            // specific hotkeys potentially send input even without
            // entering interactive mode.
            ->pty()
            ->input($this->input);

        $this->setWorkingDirectory();

        if ($this->processModifier) {
            call_user_func($this->processModifier, $process);
        }

        // Add some default env variables to hopefully
        // make output more manageable.
        return $process->env([
            'TERM' => 'xterm-256color',
            'FORCE_COLOR' => '1',
            'COLUMNS' => $this->scrollPaneWidth(),
            'LINES' => $this->scrollPaneHeight(),
            ...$process->environment
        ]);
    }

    protected function setWorkingDirectory(): void
    {
        if (!$this->workingDirectory) {
            return;
        }

        if (is_dir($this->workingDirectory)) {
            $this->withProcess(function (PendingProcess $process) {
                $process->path($this->workingDirectory);
            });

            return;
        }

        $errorBox = new ErrorBox([
            "Directory not found: {$this->workingDirectory}",
            'Please check the working directory in config.'
        ]);

        $this->addOutput($errorBox->render());

        $this->withProcess(function (PendingProcess $process) {
            return $process->command('')->input(null);
        });
    }

    public function sendInput(mixed $input)
    {
        if (!$this->input->isClosed()) {
            $this->input->write($input);
        }
    }

    public function withProcess(Closure $cb)
    {
        $this->processModifier = $cb;

        return $this;
    }

    public function autostart(): static
    {
        if ($this->autostart && $this->processStopped()) {
            $this->start();
        }

        return $this;
    }

    public function beforeStart()
    {
        //
    }

    public function start(): void
    {
        $this->beforeStart();

        $this->process = $this->createPendingProcess()->start(null, function ($type, $buffer) {
            $this->partialBuffer .= $buffer;
        });

        //         $this->sendSizeViaStty();
    }

    public function whenStopping()
    {
        //
    }

    public function stop(): void
    {
        $this->stopping = true;

        $this->whenStopping();

        if ($this->processRunning()) {
            $this->children = ProcessTracker::children($this->process->id());

            // Keep track of when we tried to stop.
            $this->stopInitiatedAt ??= Carbon::now();

            // Ask for a graceful shutdown. If it isn't
            // respected, we'll force kill it later.
            $this->process->signal(SIGTERM);
        }
    }

    public function restart(): void
    {
        $this->afterTerminate(function () {
            $this->start();
        });

        $this->stop();
    }

    public function toggle(): void
    {
        $this->processRunning() ? $this->stop() : $this->start();
    }

    public function afterTerminate($cb): static
    {
        $this->afterTerminateCallbacks[] = $cb;

        return $this;
    }

    public function processRunning(): bool
    {
        return $this->process?->running() ?? false;
    }

    public function processStopped(): bool
    {
        return !$this->processRunning();
    }

    public function sendSizeViaStty(): void
    {
        // If the process is not running or has no PID, we can’t do anything
        $pid = $this->process->id();

        if (!$pid) {
            return;
        }

        // List all open files for the child process
        $output = [];

        exec(sprintf('lsof -p %d 2>/dev/null', $pid), $output);

        foreach ($output as $line) {
            if (!preg_match('#(/dev/tty\S+|/dev/pty\S+)#', $line, $matches)) {
                continue;
            }

            $device = $matches[1];

            if ($device === '/dev/ttys000') {
                continue;
            }

            exec(sprintf(
                'stty rows %d cols %d < %s',
                $this->scrollPaneHeight(),
                $this->scrollPaneWidth(),
                escapeshellarg($device)
            ));

            break;
        }
    }

    protected function clearStdOut()
    {
        $this->withSymfonyProcess(function (SymfonyProcess $process) {
            $process->clearOutput();
        });
    }

    protected function clearStdErr()
    {
        $this->withSymfonyProcess(function (SymfonyProcess $process) {
            $process->clearErrorOutput();
        });
    }

    protected function withSymfonyProcess(Closure $callback)
    {
        /** @var SymfonyProcess $process */
        $process = (new ReflectionClass(InvokedProcess::class))
            ->getProperty('process')
            ->getValue($this->process);

        return $callback($process);
    }

    protected function marshalProcess(): void
    {
        // If we're trying to stop and the process isn't running, then we
        // succeeded. We'll reset some state and call the callbacks.
        if ($this->stopping && $this->processStopped()) {
            $this->stopping = false;
            $this->stopInitiatedAt = null;

            ProcessTracker::kill($this->children);

            $this->addLine('Stopped.');

            $this->callAfterTerminateCallbacks();

            return;
        }

        // If we're not stopping or it's not running,
        // then it doesn't qualify as rogue.
        if (!$this->stopping || $this->processStopped()) {
            return;
        }

        // We'll give it five seconds to terminate.
        if ($this->stopInitiatedAt->copy()->addSeconds(5)->isFuture()) {
            if (Carbon::now()->microsecond < 25_000) {
                $this->addLine('Waiting...');
            }

            return;
        }

        if ($this->processRunning()) {
            $this->addLine('Force killing!');

            $this->process->signal(SIGKILL);
        }
    }

    protected function callAfterTerminateCallbacks()
    {
        foreach ($this->afterTerminateCallbacks as $cb) {
            if ($cb instanceof Closure) {
                $cb = $cb->bindTo($this, static::class);
            }

            $cb();
        }

        $this->afterTerminateCallbacks = [];
    }

    protected function collectIncrementalOutput(): void
    {
        $before = strlen($this->partialBuffer);

        // A bit of a hack, but there's no other way in. Process is a Laravel InvokedProcess.
        // Calling `running` on it defers to the Symfony process `isRunning` method. That
        // method calls a protected method `updateStatus` which calls a private method
        // `readPipes` which invokes the output callback, adding it to our buffer.
        $this->process?->running();

        $after = strlen($this->partialBuffer);

        if (!$before && !$after) {
            return;
        }

        // No more data came out, so let's flush the whole thing.
        if ($before === $after) {
            $write = $this->partialBuffer;

            // @link https://github.com/aarondfrancis/solo/issues/33
            $this->clearStdOut();
            $this->clearStdErr();
        } elseif ($after > 10240) {
            if (Str::contains($this->partialBuffer, "\n")) {
                // We're over the limit, so look for a safe spot to cut, starting with newlines.
                $write = Str::beforeLast($this->partialBuffer, "\n");
            } elseif (Str::contains($this->partialBuffer, "\e")) {
                // If there aren't any, let's cut right before an ANSI code so we don't splice it.
                $write = Str::beforeLast($this->partialBuffer, "\e");
            } else {
                // Otherwise, we'll just slice anywhere that's safe.
                $write = $this->sliceAtUTF8Boundary($this->partialBuffer);
            }
        } else {
            return;
        }

        $this->partialBuffer = substr($this->partialBuffer, strlen($write));
        $this->addOutput($write);
    }

    public function sliceAtUTF8Boundary(string $input): string
    {
        $len = strlen($input);

        // Walk backward from the end, to find a safe UTF-8 start
        $i = $len - 1;
        while ($i >= 0) {
            $byteVal = ord($input[$i]);

            // If this is a leading byte or ASCII, we're good
            // Leading bytes match:
            //   0xxxxxxx (ASCII)
            //   110xxxxx (2-byte start)
            //   1110xxxx (3-byte start)
            //   11110xxx (4-byte start)
            // etc.
            if (($byteVal & 0b11000000) != 0b10000000) {
                // This is not a continuation byte (i.e. 10xxxxxx),
                // so it's a valid UTF-8 start boundary
                break;
            }

            $i--;
        }

        // Now $i is either -1 (we fell off the start) or at the start of a codepoint
        return substr($input, 0, $i + 1);
    }
}
