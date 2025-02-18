<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Integration;

use Log;
use PHPUnit\Framework\Attributes\Test;
use Str;

class BasicTest extends Base
{
    #[Test]
    public function basic_test_only()
    {
        $actions = [
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('Stopped', $plain);
                // Red
                $this->assertStringContainsString("\e[0;31;40m•", $ansi);
            },
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about'
            ]);
        });
    }

    #[Test]
    public function stop_command_test()
    {
        $actions = [
            function (string $ansi, string $plain) {
                // Green
                $this->assertStringContainsString("\e[0;32;40m•", $ansi);
                $this->assertStringContainsString(' Running: tail ', $plain);
            },
            // Press the stop hotkey
            's',
            // Assert that the Logs tab is stopped
            function (string $ansi, string $plain) {
                // Red
                $this->assertStringContainsString("\e[0;31;40m•", $ansi);
                $this->assertStringContainsString(' Stopped: tail ', $plain);
            },
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.theme', 'light');

            config()->set('solo.commands', [
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }

    #[Test]
    public function clear_output_test()
    {
        $rand = 'Testing ' . Str::random();
        Log::info($rand);

        $actions = [
            fn(string $ansi, string $plain) => $this->assertStringContainsString($rand, $ansi),
            'c',
            fn(string $ansi, string $plain) => $this->assertStringNotContainsString($rand, $ansi),
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }

    #[Test]
    public function tail_restarts_too_quickly()
    {
        $rand = 'Testing ' . Str::random();
        Log::info($rand);

        $actions = [
            fn(string $ansi, string $plain) => $this->assertStringContainsString($rand, $plain),
            'c',
            'r',
            1_500,
            fn(string $ansi, string $plain) => $this->assertStringNotContainsString('Waiting...', $plain),
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }
}
