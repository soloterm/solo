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
                $this->assertStringContainsString('Solo for Laravel is a package to run multiple', $plain);
            },
            'l',
            function (string $ansi, string $plain) {
                $this->assertStringNotContainsString('Solo for Laravel is a package to run multiple', $plain);
            },
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.keybinding', 'vim');
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about',
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }

    #[Test]
    public function hotkeys_are_bound_to_commands()
    {
        $actions = [
            Key::RIGHT_ARROW,
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('Hide Vendor', $plain);
            },
            Key::RIGHT_ARROW,
            'v',
            Key::RIGHT_ARROW,
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('Hide Vendor', $plain);
            },
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about',
                'Logs' => EnhancedTailCommand::file(storage_path('logs/laravel.log'))
            ]);
        });
    }
}
