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
    public function vim_hotkeys(): void
    {
        $this->height = 20;
        $logsCommand = $this->tailLogCommand();

        $actions = [
            function (string $ansi, string $plain) {
                $this->assertStringContainsString('h/l ', $plain);
                $this->assertStringContainsString('GitHub: https://github.com/aarondfrancis/solo', $plain);
            },
            'l',
            function (string $ansi, string $plain) {
                $this->assertStringNotContainsString('GitHub: https://github.com/aarondfrancis/solo', $plain);
            },
        ];

        $this->runSolo($actions, function () use ($logsCommand) {
            config()->set('solo.keybinding', 'vim');
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about',
                'Logs' => $logsCommand
            ]);
        });
    }

    #[Test]
    public function tab_navigation_with_arrow_keys(): void
    {
        $logsCommand = $this->tailLogCommand();

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

        $this->runSolo($actions, function () use ($logsCommand) {
            config()->set('solo.commands', [
                'About' => 'php artisan solo:about',
                'Logs' => $logsCommand
            ]);
        });
    }
}
