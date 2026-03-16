<?php

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use SoloTerm\Solo\Support\ProcessTracker;

class ProcessTrackerTest extends Base
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeProcessTracker::reset();
        FakeProcessTracker::clearCache();
    }

    protected function tearDown(): void
    {
        FakeProcessTracker::reset();
        FakeProcessTracker::clearCache();

        parent::tearDown();
    }

    #[Test]
    public function is_running_throws_for_invalid_pids(): void
    {
        $this->expectException(RuntimeException::class);

        ProcessTracker::isRunning('abc');
    }

    #[Test]
    public function is_running_accepts_the_current_process_and_rejects_missing_ones(): void
    {
        $this->assertTrue(ProcessTracker::isRunning(getmypid()));
        $this->assertFalse(ProcessTracker::isRunning(999_999));
    }

    #[Test]
    public function running_filters_to_processes_that_still_exist(): void
    {
        $running = ProcessTracker::running([getmypid()]);
        $missing = ProcessTracker::running([999_999]);

        $this->assertContains(getmypid(), $running);
        $this->assertSame([], $missing);
    }

    #[Test]
    public function children_handles_cycles_without_revisiting_seen_pids(): void
    {
        $children = FakeProcessTracker::children(1, [
            ['pid' => 2, 'ppid' => 1],
            ['pid' => 3, 'ppid' => 2],
            ['pid' => 1, 'ppid' => 3],
            ['pid' => 4, 'ppid' => 2],
        ]);

        sort($children);

        $this->assertSame([2, 3, 4], $children);
    }

    #[Test]
    public function children_respects_the_maximum_depth_limit(): void
    {
        $processes = [];

        for ($pid = 2; $pid <= 80; $pid++) {
            $processes[] = [
                'pid' => $pid,
                'ppid' => $pid - 1,
            ];
        }

        $children = ProcessTracker::children(1, $processes);

        $this->assertCount(50, $children);
        $this->assertSame(range(2, 51), $children);
    }

    #[Test]
    public function is_running_can_use_a_prefetched_process_snapshot(): void
    {
        $this->assertTrue(FakeProcessTracker::isRunning(42, [
            ['pid' => 42, 'ppid' => 1],
        ]));

        $this->assertFalse(FakeProcessTracker::isRunning(99, [
            ['pid' => 42, 'ppid' => 1],
        ]));

        $this->assertSame([], FakeProcessTracker::$signalChecks);
        $this->assertSame([], FakeProcessTracker::$executedCommands);
    }

    #[Test]
    public function running_prefers_posix_signal_checks_when_available(): void
    {
        FakeProcessTracker::$signalChecksAvailable = true;
        FakeProcessTracker::$signalResults = [
            11 => true,
            12 => false,
            13 => true,
        ];

        $running = FakeProcessTracker::running([11, 12, 13]);

        $this->assertSame([11, 13], $running);
        $this->assertSame([11, 12, 13], FakeProcessTracker::$signalChecks);
        $this->assertSame([], FakeProcessTracker::$executedCommands);
    }

    #[Test]
    public function running_falls_back_to_ps_when_signal_checks_are_unavailable(): void
    {
        FakeProcessTracker::$signalChecksAvailable = false;
        FakeProcessTracker::$commandOutputs = [
            'ps -p 11,12,13 -o pid= 2>/dev/null' => [' 12', '13'],
        ];

        $running = FakeProcessTracker::running([11, 12, 13]);

        $this->assertSame([12, 13], $running);
        $this->assertSame(['ps -p 11,12,13 -o pid= 2>/dev/null'], FakeProcessTracker::$executedCommands);
    }

    #[Test]
    public function get_process_list_uses_darwin_command(): void
    {
        FakeProcessTracker::$fakeOs = 'Darwin';
        FakeProcessTracker::$fakeNowMs = 1_000;
        FakeProcessTracker::$commandOutputs = [
            'ps -eo pid=,ppid=' => [' 2 1', ' 3 2'],
        ];

        $processes = FakeProcessTracker::getProcessList();

        $this->assertSame([
            ['pid' => 2, 'ppid' => 1],
            ['pid' => 3, 'ppid' => 2],
        ], $processes);

        $this->assertSame(['ps -eo pid=,ppid='], FakeProcessTracker::$executedCommands);
    }

    #[Test]
    public function get_process_list_uses_linux_command(): void
    {
        FakeProcessTracker::$fakeOs = 'Linux';
        FakeProcessTracker::$fakeNowMs = 1_000;
        FakeProcessTracker::$commandOutputs = [
            'ps -eo pid,ppid --no-headers' => ['2 1', '3 2'],
        ];

        $processes = FakeProcessTracker::getProcessList();

        $this->assertSame([
            ['pid' => 2, 'ppid' => 1],
            ['pid' => 3, 'ppid' => 2],
        ], $processes);

        $this->assertSame(['ps -eo pid,ppid --no-headers'], FakeProcessTracker::$executedCommands);
    }

    #[Test]
    public function get_process_list_uses_cached_values_within_ttl(): void
    {
        FakeProcessTracker::$fakeOs = 'Darwin';
        FakeProcessTracker::$fakeNowMs = 1_000;
        FakeProcessTracker::$commandOutputs = [
            'ps -eo pid=,ppid=' => [' 2 1'],
        ];

        $first = FakeProcessTracker::getProcessList();

        FakeProcessTracker::$fakeNowMs = 1_050;
        FakeProcessTracker::$commandOutputs = [
            'ps -eo pid=,ppid=' => [' 9 1'],
        ];

        $second = FakeProcessTracker::getProcessList();

        $this->assertSame($first, $second);
        $this->assertCount(1, FakeProcessTracker::$executedCommands);
    }

    #[Test]
    public function get_process_list_throws_on_unsupported_os(): void
    {
        FakeProcessTracker::$fakeOs = 'Windows';

        $this->expectException(RuntimeException::class);

        FakeProcessTracker::getProcessList();
    }

    #[Test]
    public function kill_matching_commands_only_kills_when_command_signature_matches(): void
    {
        $tracker = new class extends ProcessTracker
        {
            public static array $commands = [];

            public static array $killed = [];

            public static bool $killedGracefully = false;

            public static function commandsByPid(array $pids): array
            {
                return array_intersect_key(self::$commands, array_flip(array_map('intval', $pids)));
            }

            public static function kill(array $pids, bool $graceful = false): void
            {
                self::$killed = array_values(array_map('intval', $pids));
                self::$killedGracefully = $graceful;
            }
        };

        $tracker::$commands = [
            100 => 'new command',
            200 => 'tail -f /tmp/solo.log',
        ];

        $tracker::killMatchingCommands([
            100 => 'old command',
            200 => 'tail -f /tmp/solo.log',
            300 => 'no longer running',
        ], graceful: true);

        $this->assertSame([200], $tracker::$killed);
        $this->assertTrue($tracker::$killedGracefully);
    }

    #[Test]
    public function is_screen_command_detects_wrapper_commands_case_insensitively(): void
    {
        $this->assertTrue(ProcessTracker::isScreenCommand('screen -U -q sh -c "echo hi"'));
        $this->assertTrue(ProcessTracker::isScreenCommand(' SCREEN -dmS solo')); // leading whitespace
        $this->assertFalse(ProcessTracker::isScreenCommand('php artisan queue:work'));
    }
}

class FakeProcessTracker extends ProcessTracker
{
    protected static ?array $processListCache = null;

    protected static ?float $cacheTime = null;

    public static string $fakeOs = 'Darwin';

    public static float $fakeNowMs = 1_000;

    /** @var array<string, array<int, string>> */
    public static array $commandOutputs = [];

    /** @var array<string, int> */
    public static array $commandReturnCodes = [];

    /** @var array<int, string> */
    public static array $executedCommands = [];

    /** @var array<int, bool> */
    public static array $signalResults = [];

    public static bool $signalChecksAvailable = true;

    /** @var array<int> */
    public static array $signalChecks = [];

    public static function reset(): void
    {
        static::$fakeOs = 'Darwin';
        static::$fakeNowMs = 1_000;
        static::$commandOutputs = [];
        static::$commandReturnCodes = [];
        static::$executedCommands = [];
        static::$signalResults = [];
        static::$signalChecksAvailable = true;
        static::$signalChecks = [];
    }

    protected static function osFamily(): string
    {
        return static::$fakeOs;
    }

    protected static function nowMilliseconds(): float
    {
        return static::$fakeNowMs;
    }

    protected static function executeCommand(string $command, ?int &$returnCode = null): array
    {
        static::$executedCommands[] = $command;
        $returnCode = static::$commandReturnCodes[$command] ?? 0;

        return static::$commandOutputs[$command] ?? [];
    }

    protected static function pidExistsViaSignal(int $pid): ?bool
    {
        static::$signalChecks[] = $pid;

        if (!static::$signalChecksAvailable) {
            return null;
        }

        return static::$signalResults[$pid] ?? false;
    }
}
