<?php

namespace SoloTerm\Solo\Tests\Unit;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use SoloTerm\Solo\Console\Commands\Solo as SoloCommand;

class SoloCommandTest extends Base
{
    private string|false $originalPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPath = getenv('PATH');
    }

    protected function tearDown(): void
    {
        if ($this->originalPath === false) {
            putenv('PATH');
        } else {
            putenv("PATH={$this->originalPath}");
        }

        parent::tearDown();
    }

    #[Test]
    public function screen_version_is_detected_from_error_output_even_when_screen_exits_non_zero(): void
    {
        $logger = $this->swapCapturingLogger();

        $this->runWithFakeScreen(<<<'SH'
#!/bin/sh
echo "Screen version 4.9.0" >&2
exit 1
SH, function (): void {
            config()->set('solo.process_driver', 'screen');

            $command = new class extends SoloCommand
            {
                public function runScreenVersionCheckForTest(): void
                {
                    $this->checkScreenVersion();
                }
            };

            $command->runScreenVersionCheckForTest();
        });

        $deprecationWarning = [
            'level' => 'warning',
            'message' => 'Solo: The GNU Screen process driver is deprecated and will be removed in a future release. The native driver is now the default and does not require GNU Screen.',
        ];

        $this->assertSame([
            $deprecationWarning,
            [
                'level' => 'error',
                'message' => 'The installed version of `screen` (4.9.0) is outdated. Please upgrade to 5.0.0 or greater for best compatibility with Solo.',
            ],
        ], $logger->records);
    }

    #[Test]
    public function missing_screen_version_still_logs_detection_error(): void
    {
        $logger = $this->swapCapturingLogger();

        $this->runWithFakeScreen(<<<'SH'
#!/bin/sh
exit 1
SH, function (): void {
            config()->set('solo.process_driver', 'screen');

            $command = new class extends SoloCommand
            {
                public function runScreenVersionCheckForTest(): void
                {
                    $this->checkScreenVersion();
                }
            };

            $command->runScreenVersionCheckForTest();
        });

        $deprecationWarning = [
            'level' => 'warning',
            'message' => 'Solo: The GNU Screen process driver is deprecated and will be removed in a future release. The native driver is now the default and does not require GNU Screen.',
        ];

        $this->assertSame([
            $deprecationWarning,
            [
                'level' => 'error',
                'message' => 'Unable to determine `screen` version. Make sure `screen` is installed.',
            ],
        ], $logger->records);
    }

    /**
     * @param  callable(): void  $callback
     */
    private function runWithFakeScreen(string $script, callable $callback): void
    {
        $directory = sys_get_temp_dir() . '/solo-screen-' . bin2hex(random_bytes(5));
        $screen = $directory . '/screen';

        mkdir($directory);
        file_put_contents($screen, $script);
        chmod($screen, 0755);

        $path = $this->originalPath === false ? $directory : $directory . PATH_SEPARATOR . $this->originalPath;

        putenv("PATH={$path}");

        try {
            $callback();
        } finally {
            if ($this->originalPath === false) {
                putenv('PATH');
            } else {
                putenv("PATH={$this->originalPath}");
            }

            @unlink($screen);
            @rmdir($directory);
        }
    }

    /**
     * @return object{records: array<int, array{level: string, message: string}>}
     */
    private function swapCapturingLogger(): object
    {
        $logger = new class extends AbstractLogger
        {
            /**
             * @var array<int, array{level: string, message: string}>
             */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                ];
            }
        };

        Log::swap($logger);

        return $logger;
    }
}
