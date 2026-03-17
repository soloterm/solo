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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use SoloTerm\Screen\Screen;
use SoloTerm\Solo\Support\ErrorBox;
use SoloTerm\Solo\Support\PendingProcess;
use SoloTerm\Solo\Support\ProcessTracker;
use SoloTerm\Solo\Support\SafeBytes;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process as SymfonyProcess;

trait ManagesProcess
{
    /**
     * Maximum buffer size before forced flush.
     * Balances memory usage, responsiveness, and UTF-8 safety.
     */
    protected const MAX_BUFFER_SIZE = 10_240;

    /**
     * Minimum interval between shutdown child-process refreshes.
     * This avoids expensive process introspection on every frame while stopping.
     */
    protected const SHUTDOWN_SIGNAL_REFRESH_MS = 100;

    /**
     * Maximum time to wait for graceful shutdown before force killing.
     */
    protected const SHUTDOWN_GRACE_PERIOD_MS = 5_000;

    /**
     * Delay before surfacing "Waiting..." for slower shutdowns.
     */
    protected const WAITING_MESSAGE_DELAY_MS = 2_000;

    /**
     * Minimum interval between repeated "Waiting..." messages.
     */
    protected const WAITING_MESSAGE_INTERVAL_MS = 1_000;

    public ?InvokedProcess $process = null;

    /** @var array<int, Closure> */
    protected array $afterTerminateCallbacks = [];

    protected bool $stopping = false;

    protected ?float $stopInitiatedAtMs = null;

    protected ?Closure $processModifier = null;

    public InputStream $input;

    protected string $partialBuffer = '';

    /**
     * Tracked child processes keyed by PID with command snapshots.
     *
     * @var array<int, string>
     */
    protected array $children = [];

    /**
     * Root process PID associated with the tracked child process map.
     */
    protected ?int $childrenProcessPid = null;

    /** @var array<string, int|string> */
    protected array $environment = [];

    /**
     * Cached PTY device path for resize operations.
     */
    protected ?string $cachedPtyDevice = null;

    /**
     * PID associated with the cached PTY device.
     */
    protected ?int $cachedPtyDevicePid = null;

    /**
     * Timestamp of the last emitted "Waiting..." message.
     */
    protected ?float $lastWaitingMessageAtMs = null;

    /**
     * Timestamp of the last shutdown signal refresh.
     */
    protected ?float $lastShutdownSignalAtMs = null;

    /**
     * Flag indicating output was received in the last tick.
     * Used by Dashboard for adaptive frame rate.
     */
    protected bool $hadOutputThisTick = false;

    /**
     * Cached reflection property for InvokedProcess::$process.
     */
    protected static ?ReflectionProperty $invokedProcessProperty = null;

    /**
     * Flag to avoid repeated reflection attempts when internals are incompatible.
     */
    protected static bool $invokedProcessPropertyUnavailable = false;

    public function createPendingProcess(): PendingProcess
    {
        $this->input ??= new InputStream;

        $screen = $this->makeNewScreen();

        // We have to make our own so that we can control pty.
        $process = app(PendingProcess::class)
            ->command($this->buildCommandArray($screen))
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
            'COLUMNS' => $screen->width,
            'LINES' => $screen->height,
            ...$this->environment,
            ...$process->environment
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function buildCommandArray(Screen $screen): array
    {
        $local = $this->localeEnvironmentVariables();
        $size = sprintf('stty cols %d rows %d', $screen->width, $screen->height);

        $built = implode(' && ', [
            $local,
            $size,
            'exec ' . $this->command,
        ]);

        return ['bash', '-c', $built];
    }

    protected function localeEnvironmentVariables(): string
    {
        $locale = escapeshellarg($this->utf8Locale());

        return "export LC_ALL={$locale}; export LANG={$locale}";
    }

    protected function utf8Locale(): string
    {
        $locale = getenv('LC_ALL')
            ?: (getenv('LC_CTYPE') ?: getenv('LANG'));

        if (!$locale && function_exists('locale_get_default')) {
            $locale = locale_get_default();
        }

        if (!$locale) {
            return 'C.UTF-8';
        }

        $normalized = strtoupper($locale);

        if ($normalized === 'C' || str_contains($normalized, 'POSIX')) {
            return 'C.UTF-8';
        }

        if (stripos($locale, 'UTF-8') !== false || stripos($locale, 'UTF8') !== false) {
            return $locale;
        }

        return explode('.', $locale, 2)[0] . '.UTF-8';
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

    public function sendInput(mixed $input): void
    {
        if (!$this->input->isClosed()) {
            $this->input->write($input);
        }
    }

    public function withProcess(Closure $cb): static
    {
        $this->processModifier = $cb;

        return $this;
    }

    /**
     * @param  array<string, int|string>  $env
     */
    public function withEnv(array $env): static
    {
        $this->environment = [
            ...$this->environment,
            ...$env,
        ];

        return $this;
    }

    /**
     * @return array<string, int|string>
     */
    public function getEnvironment(): array
    {
        return $this->environment;
    }

    public function autostart(): static
    {
        if ($this->autostart && $this->processStopped()) {
            $this->start();
        }

        return $this;
    }

    public function beforeStart(): void
    {
        //
    }

    public function start(): void
    {
        // If the command is blocked, display the warning instead of starting.
        if ($this->isBlocked()) {
            $this->displayBlockedWarning();

            return;
        }

        $this->resetProcessTrackingState();

        $this->beforeStart();

        $this->process = $this->createPendingProcess()->start(null, function ($type, $buffer) {
            $this->partialBuffer .= $buffer;
        });
    }

    protected function displayBlockedWarning(): void
    {
        $errorBox = new ErrorBox(
            message: $this->getBlockedReason() ?? 'This command has been blocked.',
            title: 'Blocked',
            color: 'yellow'
        );

        $this->addOutput($errorBox->render());
    }

    public function whenStopping(): void
    {
        //
    }

    public function stop(): void
    {
        $this->stopping = true;
        $this->lastWaitingMessageAtMs = null;

        $this->whenStopping();

        if ($this->processStopped()) {
            // If restart/stop is triggered after the process already exited,
            // stale children must not leak into the next lifecycle.
            $this->resetTrackedChildren();

            return;
        }

        $this->stopInitiatedAtMs ??= $this->shutdownSignalClockMs();
        $this->sendTermSignals(force: true);
    }

    /**
     * Send SIGTERM to the running command process tree.
     * Re-enumerates children each time to catch newly spawned processes.
     */
    protected function sendTermSignals(bool $force = false): void
    {
        if (!$this->shouldDispatchShutdownSignals($force)) {
            return;
        }

        $pid = (int) ($this->process?->id() ?? 0);

        if ($pid <= 0) {
            return;
        }

        $this->markShutdownSignalsDispatched();

        // Signal the root process for graceful termination.
        ProcessTracker::signal([$pid], SIGTERM);

        if ($this->childrenProcessPid !== $pid) {
            $this->resetTrackedChildren();
            $this->childrenProcessPid = $pid;
        }

        $trackedPids = array_keys($this->children);
        $discoveredPids = ProcessTracker::children($pid);
        $activePids = ProcessTracker::running([...$trackedPids, ...$discoveredPids]);

        if (empty($activePids)) {
            $this->resetTrackedChildren();

            return;
        }

        try {
            $commandsByPid = ProcessTracker::commandsByPid($activePids);
        } catch (\RuntimeException $e) {
            Log::warning('Solo: Failed to snapshot child process commands during shutdown', [
                'pid' => $pid,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Keep only tracked PIDs whose command signature still matches.
        foreach ($this->children as $childPid => $commandSnapshot) {
            if (($commandsByPid[$childPid] ?? null) !== $commandSnapshot) {
                unset($this->children[$childPid]);
            }
        }

        // Start tracking newly discovered processes with command snapshots.
        foreach ($commandsByPid as $childPid => $command) {
            if (!isset($this->children[$childPid])) {
                $this->children[$childPid] = $command;
            }
        }

        if (!empty($this->children)) {
            ProcessTracker::signal(array_keys($this->children), SIGTERM);
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

    public function afterTerminate(Closure $cb): static
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

    /**
     * Check if output was received in the last tick.
     * Used by Dashboard for adaptive frame rate.
     */
    public function hadOutputThisTick(): bool
    {
        return $this->hadOutputThisTick;
    }

    /**
     * Check if the process is in the stopping state.
     */
    public function isStopping(): bool
    {
        return $this->stopping;
    }

    public function sendSizeViaStty(): void
    {
        $pid = $this->process?->id();

        if (!$pid) {
            return;
        }

        // Use cached PTY device if we have one for this process
        if ($this->cachedPtyDevicePid !== $pid) {
            $this->cachedPtyDevice = $this->discoverPtyDevice($pid);
            $this->cachedPtyDevicePid = $pid;
        }

        if (!$this->cachedPtyDevice) {
            return;
        }

        exec(sprintf(
            'stty rows %d cols %d < %s 2>/dev/null',
            $this->scrollPaneHeight(),
            $this->scrollPaneWidth(),
            escapeshellarg($this->cachedPtyDevice)
        ));
    }

    /**
     * Discover the PTY device for a given process ID.
     */
    protected function discoverPtyDevice(int $pid): ?string
    {
        // Detect current TTY to skip (the controlling terminal for this PHP process)
        $currentTty = null;
        if (function_exists('posix_ttyname')) {
            $currentTty = @posix_ttyname(STDIN);
        } elseif (PHP_OS_FAMILY !== 'Windows') {
            $tty = @shell_exec('tty 2>/dev/null');
            $currentTty = $tty ? trim($tty) : null;
        }

        $output = [];
        exec(sprintf('lsof -p %d 2>/dev/null', $pid), $output);

        foreach ($output as $line) {
            // Match /dev/tty*, /dev/pty*, and /dev/pts/* (Linux pseudo-terminals)
            if (!preg_match('#(/dev/(tty\S+|pty\S+|pts/\d+))#', $line, $matches)) {
                continue;
            }

            $device = $matches[1];

            // Skip the main terminal device for this PHP process
            if ($currentTty && $device === $currentTty) {
                continue;
            }

            return $device;
        }

        return null;
    }

    protected function clearStdOut(): void
    {
        $this->withSymfonyProcess(function (SymfonyProcess $process) {
            $process->clearOutput();
        });
    }

    protected function clearStdErr(): void
    {
        $this->withSymfonyProcess(function (SymfonyProcess $process) {
            $process->clearErrorOutput();
        });
    }

    /**
     * Access the underlying Symfony Process via reflection.
     * Includes safety checks for framework compatibility.
     */
    protected function withSymfonyProcess(Closure $callback): mixed
    {
        if (!$this->process) {
            return null;
        }

        if (static::$invokedProcessPropertyUnavailable) {
            return null;
        }

        try {
            if (static::$invokedProcessProperty === null) {
                $reflection = new ReflectionClass(InvokedProcess::class);

                if (!$reflection->hasProperty('process')) {
                    Log::warning('Solo: InvokedProcess internal structure may have changed - missing process property');
                    static::$invokedProcessPropertyUnavailable = true;

                    return null;
                }

                static::$invokedProcessProperty = $reflection->getProperty('process');
            }

            $symfonyProcess = static::$invokedProcessProperty->getValue($this->process);

            if (!$symfonyProcess instanceof SymfonyProcess) {
                Log::warning('Solo: InvokedProcess internal structure may have changed - unexpected type');
                static::$invokedProcessPropertyUnavailable = true;

                return null;
            }

            return $callback($symfonyProcess);
        } catch (ReflectionException $e) {
            Log::warning('Solo: Failed to access Symfony process', ['error' => $e->getMessage()]);
            static::$invokedProcessPropertyUnavailable = true;

            return null;
        }
    }

    protected function marshalProcess(): void
    {
        // If we're trying to stop and the process isn't running, then we
        // succeeded. We'll reset some state and call the callbacks.
        if ($this->stopping && $this->processStopped()) {
            $trackedChildren = $this->children;

            $this->stopping = false;

            try {
                ProcessTracker::killMatchingCommands($trackedChildren);
            } catch (\RuntimeException $e) {
                Log::warning('Solo: Failed to verify child commands before cleanup', [
                    'error' => $e->getMessage(),
                ]);
            }

            $this->resetProcessTrackingState();

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
        if ($this->shutdownGracePeriodHasNotExpired()) {
            // Re-send SIGTERM to any new children spawned during grace period
            $this->sendTermSignals();

            if ($this->shouldEmitWaitingMessage()) {
                $this->addLine('Waiting...');
            }

            return;
        }

        if ($this->processRunning()) {
            $this->addLine('Force killing!');

            $this->process->signal(SIGKILL);
        }
    }

    protected function resetProcessTrackingState(): void
    {
        $this->stopInitiatedAtMs = null;
        $this->resetTrackedChildren();
        $this->cachedPtyDevice = null;
        $this->cachedPtyDevicePid = null;
        $this->lastWaitingMessageAtMs = null;
        $this->lastShutdownSignalAtMs = null;
        $this->partialBuffer = '';
        $this->hadOutputThisTick = false;
    }

    protected function resetTrackedChildren(): void
    {
        $this->children = [];
        $this->childrenProcessPid = null;
    }

    protected function shouldDispatchShutdownSignals(bool $force = false): bool
    {
        if ($force || $this->lastShutdownSignalAtMs === null) {
            return true;
        }

        return ($this->shutdownSignalClockMs() - $this->lastShutdownSignalAtMs) >= static::SHUTDOWN_SIGNAL_REFRESH_MS;
    }

    protected function markShutdownSignalsDispatched(): void
    {
        $this->lastShutdownSignalAtMs = $this->shutdownSignalClockMs();
    }

    protected function shutdownSignalClockMs(): float
    {
        return function_exists('hrtime')
            ? hrtime(true) / 1_000_000
            : microtime(true) * 1000;
    }

    protected function shutdownGracePeriodHasNotExpired(): bool
    {
        return $this->stopInitiatedAtMs !== null
            && ($this->shutdownSignalClockMs() - $this->stopInitiatedAtMs) < static::SHUTDOWN_GRACE_PERIOD_MS;
    }

    protected function shouldEmitWaitingMessage(): bool
    {
        if ($this->stopInitiatedAtMs === null) {
            return false;
        }

        $nowMs = $this->shutdownSignalClockMs();

        if (($nowMs - $this->stopInitiatedAtMs) < static::WAITING_MESSAGE_DELAY_MS) {
            return false;
        }

        if ($this->lastWaitingMessageAtMs !== null
            && ($nowMs - $this->lastWaitingMessageAtMs) < static::WAITING_MESSAGE_INTERVAL_MS) {
            return false;
        }

        $this->lastWaitingMessageAtMs = $nowMs;

        return true;
    }

    protected function callAfterTerminateCallbacks(): void
    {
        foreach ($this->afterTerminateCallbacks as $cb) {
            $cb->bindTo($this, static::class)?->__invoke();
        }

        $this->afterTerminateCallbacks = [];
    }

    protected function collectIncrementalOutput(): void
    {
        // Reset the activity flag at the start of each collection cycle
        $this->hadOutputThisTick = false;

        $before = strlen($this->partialBuffer);

        // Explicitly trigger output collection by reading incremental output.
        // This is more reliable than relying on running() side effects.
        $this->withSymfonyProcess(function (SymfonyProcess $process) {
            // These calls trigger pipe reading and invoke our output callback
            $process->getIncrementalOutput();
            $process->getIncrementalErrorOutput();
        });

        // Also check running status (needed for flush logic below)
        $running = $this->process?->running();

        $after = strlen($this->partialBuffer);

        // Track if we received new output this tick
        if ($after > $before) {
            $this->hadOutputThisTick = true;
        }

        if (!$before && !$after) {
            return;
        }

        // No more data came out, so let's flush the whole thing.
        if ($before === $after) {
            $write = $this->partialBuffer;

            // @link https://github.com/aarondfrancis/solo/issues/33
            $this->clearStdOut();
            $this->clearStdErr();
        } elseif ($after > static::MAX_BUFFER_SIZE) {
            if (Str::contains($this->partialBuffer, "\n")) {
                // We're over the limit, so look for a safe spot to cut, starting with newlines.
                $write = Str::beforeLast($this->partialBuffer, "\n");
            } elseif (Str::contains($this->partialBuffer, "\e")) {
                // If there aren't any, let's cut right before an ANSI code so we don't splice it.
                $write = Str::beforeLast($this->partialBuffer, "\e");
            } else {
                // Otherwise, we'll just slice anywhere that's safe.
                $write = $this->sliceBeforeLogicalCharacterBoundary($this->partialBuffer);
            }
        } else {
            return;
        }

        // When a process is killed, it's entirely possible that we don't have very much output
        // to write but still could've spliced a multibyte character. This will cause failure
        // further down the line. Here we get only the non-spliced bytes. If we haven't
        // spliced anything this method returns everything as is, which is the hope!
        if (!$running) {
            $write = head(SafeBytes::parse($write));
        }

        $this->partialBuffer = substr($this->partialBuffer, strlen($write));

        $this->addOutput($write);
    }

    public function sliceBeforeLogicalCharacterBoundary(string $input): string
    {
        // The pattern \X is a PCRE escape that matches an extended
        // grapheme cluster—that is, a complete visual unit.
        // We must use grapheme clusters (not just UTF-8 boundaries) because
        // characters like emoji with variation selectors are multiple code points.
        $success = preg_match_all("/\X/u", $input, $matches);

        // If the regex failed, we'll try to use our SafeBytes class
        // to figure out where we spliced a multibyte character.
        if (!$success) {
            return head(SafeBytes::parse($input));
        }

        // Return everything before the last grapheme cluster.
        return implode('', array_splice($matches[0], 0, -1));
    }
}
