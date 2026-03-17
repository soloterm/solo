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
    public function run_solo_command_in_directory(): void
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

                // Guard against regressions that shift command output far out
                // of view in the flattened ANSI capture used by integration tests.
                $position = strpos($plain, 'solo.php');
                $this->assertNotFalse($position);
                $this->assertLessThan(400, $position);
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
