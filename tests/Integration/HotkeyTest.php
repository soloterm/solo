<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Integration;

use Laravel\Prompts\Key;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Commands\EnhancedTailCommand;

class HotkeyTest extends Base
{
    #[Test]
    public function vim_hotkeys()
    {
        $actions = [
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('h/l ', $plain);
                $this->assertStringContainsString('j/k ', $plain);
                $this->assertStringContainsString('GitHub: https://github.com/aarondfrancis/solo', $plain);
            },
            'l',
            function (string $ansi, string $plain) {
                $this->assertStringNotContainsString('GitHub: https://github.com/aarondfrancis/solo', $plain);
            },
            '$',    // expected to move to the tail tab
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan test', $plain);
            },
            'l',    // expected to move to the head tab
            'h',    // expected to move to the tail tab
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan test', $plain);
            },
            'h',
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan queue:work', $plain);
            },
            '^',    // expected to move to the head tab
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('GitHub: https://github.com/aarondfrancis/solo', $plain);
            },
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.keybinding', 'vim');
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about',
                'Logs' => EnhancedTailCommand::file(storage_path('logs/laravel.log')),
                'Queue' => Command::from('php artisan queue:work')->lazy(),
                'Test' => Command::from('php artisan test')->lazy(),
            ]);
        });
    }

    #[Test]
    public function hotkeys_are_bound_to_commands()
    {
        $actions = [
            Key::RIGHT_ARROW,
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('Hide vendor', $plain);
            },
            'v',
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('Show vendor', $plain);
            },
            Key::RIGHT_ARROW,
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan queue:work', $plain);
            },
            "\e[1;5D",  // CTRL + LEFT_ARROW : expected to move to the head tab
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan solo:about', $plain);
            },
            Key::RIGHT_ARROW,
            "\e[1;5C",  // CTRL + RIGHT_ARROW : expected to move to the tail tab
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan test', $plain);
            },
            Key::RIGHT_ARROW,   // expected to move to the head tab
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan solo:about', $plain);
            },
            Key::LEFT_ARROW,    // expected to move to the tail tab
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('php artisan test', $plain);
            },
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about',
                'Logs' => EnhancedTailCommand::file(storage_path('logs/laravel.log')),
                'Queue' => Command::from('php artisan queue:work')->lazy(),
                'Test' => Command::from('php artisan test')->lazy(),
            ]);
        });
    }
}
