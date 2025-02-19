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
            's', function ($ansi, $plain) {
                $this->assertStringContainsString('List', $plain);
                $this->assertStringContainsString('solo.php', $plain);
            },
            Key::LEFT, fn($plain) => $this->assertStringContainsString('Vue3', $plain),
            's', fn($plain) => $this->assertStringContainsString('Directory not found: resources/js/vue3', $plain),
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'List' => Command::from('ls')
                    ->inDirectory('config'),
                'Vue3' => Command::from('npm run dev')
                    ->inDirectory('resources/js/vue3'),
            ]);
        });

    }
}
