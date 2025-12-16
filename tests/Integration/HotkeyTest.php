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
    public function tab_navigation_with_arrow_keys()
    {
        $actions = [
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('About', $plain);
            },
            Key::RIGHT_ARROW,
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('Logs', $plain);
            },
            Key::LEFT_ARROW,
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('About', $plain);
            },
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about',
                'Logs' => 'tail -f -n 100 ' . storage_path('logs/laravel.log')
            ]);
        });
    }
}
