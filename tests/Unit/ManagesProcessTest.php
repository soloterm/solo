<?php

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;
use Symfony\Component\Process\Process as SymfonyProcess;

class ManagesProcessTest extends Base
{
    #[Test]
    public function commands_are_wrapped_with_locale_and_stty_bootstrap(): void
    {
        $command = new class(name: 'Tests', command: 'APP_ENV=testing php artisan test --filter="User Test"') extends Command
        {
            /**
             * @return array{built: array<int, string>, width: int, height: int}
             */
            public function buildWithDimensions(): array
            {
                $this->setDimensions(100, 40);

                return [
                    'built' => $this->buildCommandArray($this->screen),
                    'width' => $this->screen->width,
                    'height' => $this->screen->height,
                ];
            }
        };

        $result = $command->buildWithDimensions();

        $this->assertSame('bash', $result['built'][0]);
        $this->assertSame('-lc', $result['built'][1]);
        $this->assertStringContainsString(
            sprintf('stty cols %d rows %d', $result['width'], $result['height']),
            $result['built'][2]
        );
        $this->assertStringContainsString('exec APP_ENV=testing php artisan test --filter="User Test"', $result['built'][2]);
    }

    #[Test]
    public function output_passes_through_without_filtering(): void
    {
        $command = new Command(name: 'Demo', command: 'echo ok');
        $command->setDimensions(120, 40);

        $payload = 'keep [[==SOLO_START==]] and [[==SOLO_END==]] intact';
        $command->addOutput($payload);

        $this->assertStringContainsString($payload, implode("\n", $command->wrappedLines()->all()));
    }

    #[Test]
    public function preserves_ansi_output(): void
    {
        $this->skipUnlessPtyIsSupported();

        $ansiCommand = "php -r '\$line = trim(fgets(STDIN)); echo \"\\033[31m\" . \$line . \"\\033[0m\\n\"; usleep(200000);'";

        $command = Command::from($ansiCommand)->interactive();
        $command->setDimensions(120, 40);
        $command->start();

        try {
            $command->sendInput("\e[31mRED\e[0m\n");

            $deadline = microtime(true) + 2.0;

            do {
                $command->onTick();
                $output = implode("\n", $command->wrappedLines()->all());

                if (str_contains($output, 'RED')) {
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            $output = implode("\n", $command->wrappedLines()->all());

            $this->assertStringContainsString('RED', $output);
            $this->assertMatchesRegularExpression('/(?:\e|\^\[)\[[0-9;]*mRED/', $output);
        } finally {
            if ($command->processRunning()) {
                $command->process?->signal(SIGKILL);
            }
        }
    }

    #[Test]
    public function supports_interactive_input_and_graceful_shutdown(): void
    {
        $this->skipUnlessPtyIsSupported();

        $command = Command::from('cat')->interactive();
        $command->setDimensions(120, 40);
        $command->start();

        try {
            $command->sendInput("solo-native\n");

            $deadline = microtime(true) + 2.0;
            $foundEcho = false;

            do {
                $command->onTick();
                $output = implode("\n", $command->wrappedLines()->all());

                if (str_contains($output, 'solo-native')) {
                    $foundEcho = true;
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            $this->assertTrue($foundEcho, 'PTY command did not echo interactive input.');

            $command->stop();

            $deadline = microtime(true) + 2.5;

            do {
                $command->onTick();

                if ($command->processStopped() && !$command->isStopping()) {
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            $this->assertTrue($command->processStopped(), 'PTY command did not stop in time.');
            $this->assertFalse($command->isStopping(), 'Command should leave stopping state once terminated.');
            $this->assertStringNotContainsString('Force killing!', implode("\n", $command->wrappedLines()->all()));
        } finally {
            if ($command->processRunning()) {
                $command->process?->signal(SIGKILL);
            }
        }
    }

    #[Test]
    public function surfaces_command_not_found_errors(): void
    {
        $this->skipUnlessPtyIsSupported();

        $missingCommand = 'solo_missing_command_' . bin2hex(random_bytes(4));

        $command = Command::from($missingCommand);
        $command->setDimensions(120, 40);
        $command->start();

        try {
            $deadline = microtime(true) + 2.0;

            do {
                $command->onTick();
                $output = implode("\n", $command->wrappedLines()->all());

                if ($command->processStopped() && str_contains($output, $missingCommand)) {
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            $output = implode("\n", $command->wrappedLines()->all());

            $this->assertStringContainsString($missingCommand, $output);
            $this->assertMatchesRegularExpression('/(?:not found|No such file|could not be found)/i', $output);
        } finally {
            if ($command->processRunning()) {
                $command->process?->signal(SIGKILL);
            }
        }
    }

    #[Test]
    public function process_tracking_state_can_be_reset_without_running_a_process(): void
    {
        $command = new class(name: 'Demo', command: 'echo ok') extends Command
        {
            public function seedTrackingState(): void
            {
                $this->stopInitiatedAtMs = 999.0;
                $this->children = [101 => 'tail -f /tmp/demo.log'];
                $this->childrenProcessPid = 42;
                $this->cachedPtyDevice = '/dev/pts/3';
                $this->cachedPtyDevicePid = 202;
                $this->lastWaitingMessageAtMs = 1_111.0;
                $this->lastShutdownSignalAtMs = 1_234.0;
                $this->partialBuffer = 'leftover';
                $this->hadOutputThisTick = true;
            }

            public function resetTrackingStateForTest(): void
            {
                $this->resetProcessTrackingState();
            }

            /**
             * @return array{
             *     stopInitiatedAtMs: ?float,
             *     children: array<int, string>,
             *     childrenProcessPid: ?int,
             *     cachedPtyDevice: ?string,
             *     cachedPtyDevicePid: ?int,
             *     lastWaitingMessageAtMs: ?float,
             *     lastShutdownSignalAtMs: ?float,
             *     partialBuffer: string,
             *     hadOutputThisTick: bool
             * }
             */
            public function trackingState(): array
            {
                return [
                    'stopInitiatedAtMs' => $this->stopInitiatedAtMs,
                    'children' => $this->children,
                    'childrenProcessPid' => $this->childrenProcessPid,
                    'cachedPtyDevice' => $this->cachedPtyDevice,
                    'cachedPtyDevicePid' => $this->cachedPtyDevicePid,
                    'lastWaitingMessageAtMs' => $this->lastWaitingMessageAtMs,
                    'lastShutdownSignalAtMs' => $this->lastShutdownSignalAtMs,
                    'partialBuffer' => $this->partialBuffer,
                    'hadOutputThisTick' => $this->hadOutputThisTick,
                ];
            }
        };

        $command->seedTrackingState();
        $command->resetTrackingStateForTest();

        $this->assertSame([
            'stopInitiatedAtMs' => null,
            'children' => [],
            'childrenProcessPid' => null,
            'cachedPtyDevice' => null,
            'cachedPtyDevicePid' => null,
            'lastWaitingMessageAtMs' => null,
            'lastShutdownSignalAtMs' => null,
            'partialBuffer' => '',
            'hadOutputThisTick' => false,
        ], $command->trackingState());
    }

    #[Test]
    public function shutdown_signal_refreshes_are_throttled_during_the_grace_period(): void
    {
        $command = new class extends Command
        {
            public float $fakeNowMs = 0;

            public int $signalDispatches = 0;

            public function processRunning(): bool
            {
                return true;
            }

            public function runMarshalProcess(): void
            {
                $this->marshalProcess();
            }

            public function seedTrackedChildren(): void
            {
                $this->children = [123 => 'tail -f /tmp/demo.log'];
            }

            protected function shutdownSignalClockMs(): float
            {
                return $this->fakeNowMs;
            }

            protected function sendTermSignals(bool $force = false): void
            {
                if (!$this->shouldDispatchShutdownSignals($force)) {
                    return;
                }

                $this->markShutdownSignalsDispatched();
                $this->signalDispatches++;
            }
        };

        $command->setDimensions(100, 40);
        $command->seedTrackedChildren();
        $command->fakeNowMs = 1_000;

        $command->stop();

        $this->assertSame(1, $command->signalDispatches);

        $command->fakeNowMs = 1_050;
        $command->runMarshalProcess();
        $this->assertSame(1, $command->signalDispatches);

        $command->fakeNowMs = 1_099;
        $command->runMarshalProcess();
        $this->assertSame(1, $command->signalDispatches);

        $command->fakeNowMs = 1_100;
        $command->runMarshalProcess();
        $this->assertSame(2, $command->signalDispatches);
    }

    #[Test]
    public function waiting_messages_are_delayed_for_quick_shutdowns(): void
    {
        $command = new class extends Command
        {
            public float $fakeNowMs = 0;

            public function processRunning(): bool
            {
                return true;
            }

            public function runMarshalProcess(): void
            {
                $this->marshalProcess();
            }

            protected function shutdownSignalClockMs(): float
            {
                return $this->fakeNowMs;
            }

            protected function sendTermSignals(bool $force = false): void
            {
                if (!$this->shouldDispatchShutdownSignals($force)) {
                    return;
                }

                $this->markShutdownSignalsDispatched();
            }
        };

        $command->setDimensions(100, 40);
        $command->fakeNowMs = 1_000;
        $command->stop();

        $command->fakeNowMs = 2_999;
        $command->runMarshalProcess();
        $this->assertStringNotContainsString('Waiting...', implode("\n", $command->wrappedLines()->all()));

        $command->fakeNowMs = 3_000;
        $command->runMarshalProcess();
        $this->assertStringContainsString('Waiting...', implode("\n", $command->wrappedLines()->all()));
    }

    #[Test]
    public function stop_clears_tracked_children_when_process_has_already_stopped(): void
    {
        $command = new class extends Command
        {
            public function processRunning(): bool
            {
                return false;
            }

            /**
             * @param  array<int, string>  $children
             */
            public function seedTrackedChildren(array $children, ?int $pid = null): void
            {
                $this->children = $children;
                $this->childrenProcessPid = $pid;
            }

            /**
             * @return array<int, string>
             */
            public function trackedChildren(): array
            {
                return $this->children;
            }

            public function trackedChildrenPid(): ?int
            {
                return $this->childrenProcessPid;
            }

            public function runMarshalProcess(): void
            {
                $this->marshalProcess();
            }
        };

        $command->setDimensions(100, 40);
        $command->seedTrackedChildren([111 => 'tail -f /tmp/foo.log'], 999);

        $command->stop();

        $this->assertSame([], $command->trackedChildren());
        $this->assertNull($command->trackedChildrenPid());

        $command->runMarshalProcess();

        $this->assertFalse($command->isStopping());
        $this->assertStringContainsString('Stopped.', implode("\n", $command->wrappedLines()->all()));
    }

    protected function skipUnlessPtyIsSupported(): void
    {
        if (!SymfonyProcess::isPtySupported()) {
            $this->markTestSkipped('PTY is not supported in this environment.');
        }
    }
}
