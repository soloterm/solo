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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
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

    public ?InvokedProcess $process = null;

    public $outputStartMarker = '[[==SOLO_START==]]';

    public $outputEndMarker = '[[==SOLO_END==]]';

    protected array $afterTerminateCallbacks = [];

    protected bool $stopping = false;

    protected ?Carbon $stopInitiatedAt = null;

    protected ?Closure $processModifier = null;

    public InputStream $input;

    protected string $partialBuffer = '';

    protected $children = [];

    protected $environment = [];

    /**
     * Cached PTY device path for resize operations.
     */
    protected ?string $cachedPtyDevice = null;

    /**
     * PID associated with the cached PTY device.
     */
    protected ?int $cachedPtyDevicePid = null;

    /**
     * Counter for rate-limiting "Waiting..." messages.
     */
    protected int $waitingMessageCounter = 0;

    /**
     * Flag indicating output was received in the last tick.
     * Used by Dashboard for adaptive frame rate.
     */
    protected bool $hadOutputThisTick = false;

    public function createPendingProcess(): PendingProcess
    {
        $this->input ??= new InputStream;

        $command = explode(' ', $this->command);

        // Resources about screen version needing to be 5.0.0
        // @TODO add a check on startup to see what version `screen` they are using
        // https://chatgpt.com/share/67b7b74e-3db8-8011-9e2b-79deb71eb12d

        // ??
        // alias screen='TERM=xterm-256color screen'
        // https://superuser.com/questions/800126/gnu-screen-changes-vim-syntax-highlighting-colors
        // https://github.com/derailed/k9s/issues/2810

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

    protected function buildCommandArray(Screen $screen): array
    {
        $useGnuScreen = (bool) Config::get('solo.use_screen', true);

        // https://github.com/soloterm/solo/pull/81
        if (!$useGnuScreen) {
            return explode(' ', $this->command);
        }

        $local = $this->localeEnvironmentVariables();
        $size = sprintf('stty cols %d rows %d', $screen->width, $screen->height);

        // If there's already content in the screen then we have to do a bit of trickery. `screen` relies
        // on absolute move codes like \e[3;1H. If we don't echo these newlines in, then the absolute
        // moves will be wrong. We echo as many newlines as are currently present in the screen.

        // We echo those *before* the outputStartMarker, so they never make it back into our Screen
        // instance, which is correct. We also add a single line to the screen itself to make
        // sure we're clear of the existing content.
        if ($lines = count($this->screen->printable->buffer)) {
            $newlines = str_repeat("\n", $lines);
            $this->screen->write("\n");
        } else {
            $newlines = '';
        }

        // We have to add a 250ms delay because some commands can print so much
        // output that screen will terminate before PHP can grab it all.
        // 250ms seems to work, although it's totally arbitrary.
        $inner = sprintf("printf '%%s' %s; %s; sleep 0.25; printf '%%s' %s",
            // `screen` spams output with a bunch of ANSI codes that we want to ignore.
            escapeshellarg($newlines . $this->outputStartMarker),
            $this->command,
            // `screen` prints "[screen is terminating]" along with more ANSI codes.
            $this->outputEndMarker
        );

        $built = implode(' && ', [
            $local,
            $size,
            'screen -U -q sh -c ' . escapeshellarg($inner)
        ]);

        return ['bash', '-c', $built];
    }

    protected function localeEnvironmentVariables()
    {
        $locale = escapeshellarg($this->utf8Locale());

        return "export LC_ALL={$locale}; export LANG={$locale}";
    }

    protected function utf8Locale()
    {
        $locale = function_exists('locale_get_default')
            ? locale_get_default()
            : (getenv('LC_ALL') ?: (getenv('LC_CTYPE') ?: getenv('LANG')));

        if (!$locale) {
            return 'en_US.UTF-8';
        }

        if (stripos($locale, 'UTF-8') !== false) {
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

    public function withEnv(array $env)
    {
        $this->environment = $env;

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
    }

    public function whenStopping()
    {
        //
    }

    public function stop(): void
    {
        $this->stopping = true;
        $this->waitingMessageCounter = 0;

        $this->whenStopping();

        if ($this->processRunning()) {
            $this->stopInitiatedAt ??= Carbon::now();
            $this->sendTermSignals();
        }
    }

    /**
     * Send SIGTERM to child processes (excluding Screen wrapper).
     * Re-enumerates children each time to catch newly spawned processes.
     */
    protected function sendTermSignals(): void
    {
        $pid = $this->process?->id();

        if (!$pid) {
            return;
        }

        // Re-enumerate children to catch any new ones spawned since last check
        $this->children = array_unique(array_merge(
            $this->children,
            ProcessTracker::children($pid)
        ));

        foreach ($this->children as $childPid) {
            // Get command name, handling potential failures gracefully
            $command = @shell_exec("ps -o command= -p $childPid 2>/dev/null");

            if ($command === null || $command === false) {
                // Process may have already exited
                continue;
            }

            $command = trim($command);

            // Skip the Screen wrapper process itself
            if (!Str::startsWith($command, 'screen') && !Str::startsWith($command, 'SCREEN')) {
                posix_kill((int) $childPid, SIGTERM);
            }
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
        $output = [];
        exec(sprintf('lsof -p %d 2>/dev/null', $pid), $output);

        foreach ($output as $line) {
            if (!preg_match('#(/dev/tty\S+|/dev/pty\S+)#', $line, $matches)) {
                continue;
            }

            $device = $matches[1];

            // Skip the main terminal device
            if ($device === '/dev/ttys000') {
                continue;
            }

            return $device;
        }

        return null;
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

    /**
     * Access the underlying Symfony Process via reflection.
     * Includes safety checks for framework compatibility.
     */
    protected function withSymfonyProcess(Closure $callback)
    {
        if (!$this->process) {
            return null;
        }

        try {
            $reflection = new ReflectionClass(InvokedProcess::class);

            if (!$reflection->hasProperty('process')) {
                Log::warning('Solo: InvokedProcess internal structure may have changed - missing process property');

                return null;
            }

            $property = $reflection->getProperty('process');
            $symfonyProcess = $property->getValue($this->process);

            if (!$symfonyProcess instanceof SymfonyProcess) {
                Log::warning('Solo: InvokedProcess internal structure may have changed - unexpected type');

                return null;
            }

            return $callback($symfonyProcess);
        } catch (ReflectionException $e) {
            Log::warning('Solo: Failed to access Symfony process', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function marshalProcess(): void
    {
        // If we're trying to stop and the process isn't running, then we
        // succeeded. We'll reset some state and call the callbacks.
        if ($this->stopping && $this->processStopped()) {
            $this->stopping = false;
            $this->stopInitiatedAt = null;
            $this->waitingMessageCounter = 0;

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
            // Re-send SIGTERM to any new children spawned during grace period
            $this->sendTermSignals();

            // Rate limit "Waiting..." messages (every ~1 second at 40 FPS)
            if ($this->waitingMessageCounter++ % 40 === 0) {
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
