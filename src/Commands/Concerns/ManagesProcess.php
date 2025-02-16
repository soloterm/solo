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

        // We have to make our own so that we can control pty.
        $process = app(PendingProcess::class)
            ->command($command)
            ->forever()
            ->timeout(0)
            ->idleTimeout(0)
            // Regardless of whether or not it's an interactive process, we're
            // still going to register an input stream. This lets command-
            // specific hotkeys potentially send input even without
            // entering interactive mode.
            ->pty()
            ->input($this->input);

        if ($this->workingDirectory && !is_dir($this->workingDirectory)) {
            $errorBox = new ErrorBox([
                    "Directory not found: {$this->workingDirectory}",
                    "Please check the working directory in config"
                ]);

            $this->addOutput($errorBox->render());

            return $process->command('')
                ->input(null);
        }

        if ($this->workingDirectory && is_dir($this->workingDirectory)) {
            $this->withProcess(function (PendingProcess $process) {
                $process->path($this->workingDirectory);
            });
        }

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

    public function start(): void
    {
        $this->process = $this->createPendingProcess()->start(null, function ($type, $buffer) {
            // After many, many hours of frustration I've figured out that for some reason the max
            // number of bytes that come through at any time is 1024. I think it has to do with
            // stdio buffering (https://www.pixelbeat.org/programming/stdio_buffering).

            // According to that article, it could be 1024 or 4096, depending on whether a terminal
            // is connected or not. We'll check both along with 2048. If there are more than 1024
            // bytes in stdout, they might end up in stderr! No idea why. Not sure if that's
            // a Symfony thing or just normal system stuff. Regardless, for that reason we
            // don't differentiate between stdout and stderr here and listen for both.

            // So when we do get a chunk that seems like it might have a continuation, we need
            // to buffer it, because there's more output coming right behind it. If we don't
            // buffer, we could splice a multibyte character or an ANSI code. Much effort
            // went into fixing byte splices, but ANSI splices are way tougher. Checking
            // if it's a perfect multiple of 1024 seems to be foolproof. Hopefully.
            if (strlen($buffer) % 1024 === 0) {
                $this->partialBuffer .= $buffer;

                // @TODO add a timer to just force the partial buffer through, in case
                // it's a legit block of 1024 bytes with nothing coming after.
                return;
            }

            $this->addOutput($this->partialBuffer . $buffer);
            $this->partialBuffer = '';

            // 5% chance of clearing the buffer. Hopefully this helps save memory.
            // @link https://github.com/aarondfrancis/solo/issues/33
            if (rand(1, 100) < 5) {
                $type === SymfonyProcess::OUT ? $this->clearStdOut() : $this->clearStdErr();
            }
        });

        $this->sendSizeViaStty();
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
        $this->callPrivateMethodOnSymfonyProcess('clearOutput');
    }

    protected function clearStdErr()
    {
        $this->callPrivateMethodOnSymfonyProcess('clearErrorOutput');
    }

    protected function callPrivateMethodOnSymfonyProcess($method, array $args = []): mixed
    {
        return $this->withSymfonyProcess(function (SymfonyProcess $process) use ($method, $args) {
            return (new ReflectionClass(SymfonyProcess::class))->getMethod($method)->invoke($process, ...$args);
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

    protected function collectIncrementalOutput()
    {
        // A bit of a hack, but there's no other way in. Process is a Laravel InvokedProcess.
        // Calling `running` on it defers to the Symfony process `isRunning` method. That
        // method calls a protected method `updateStatus` which calls a private method
        // `readPipes` which invokes the output callback, adding it to our buffer.
        $this->process?->running();
    }
}
