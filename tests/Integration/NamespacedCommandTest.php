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

class NamespacedCommandTest extends Base
{
    #[Test]
    public function run_solo_command_in_directory()
    {
        $actions = [
            's',
            function ($ansi, $plain) {
                if (!str_contains($plain, 'solo.php')) {
                    yield 250;

                    return;
                }

                $this->assertStringContainsString('List', $plain);
                $this->assertStringContainsString('solo.php', $plain);

                // We've had some issues where there are newlines above the output,
                // so this ensures that we dont have that regression.
                $lines = explode("\n", $plain);

                $this->assertArrayHasKey(3, $lines);
                $this->assertStringContainsString('solo.php', $lines[3]);
            },
            Key::LEFT,
            fn($plain) => $this->assertStringContainsString('Vue3', $plain),
            's',
            fn($plain) => $this->assertStringContainsString('Directory not found: resources/js/vue3', $plain),
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'List' => Command::from('ls')->inDirectory('config'),
                'Vue3' => Command::from('npm run dev')->inDirectory('resources/js/vue3'),
            ]);
        });

    }
}
