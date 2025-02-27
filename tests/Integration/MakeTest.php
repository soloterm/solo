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
use SoloTerm\Solo\Commands\MakeCommand;

class MakeTest extends Base
{
    #[Test]
    public function basic_test()
    {
        $actions = [

            'i',
            fn($plain) => $this->assertStringContainsString('Interactive', $plain),
            'Mo',
            fn($plain) => $this->assertStringContainsString('Model', $plain),
            Key::DOWN,
            Key::ENTER,
            fn($plain) => $this->assertStringContainsString('What should the model be named', $plain),
            Key::CTRL_C,
            fn($plain) => $this->assertStringContainsString('Make another class', $plain),
            fn($plain) => $this->assertStringContainsString('Exit interactive mode', $plain),
            "\x18",
            fn($plain) => $this->assertStringContainsString('Enter interactive mode', $plain),
        ];

        $this->runSolo($actions, function () {
            config()->set('solo.commands', [
                'Make' => MakeCommand::class,
            ]);
        });
    }
}
