<?php

namespace SoloTerm\Solo\Tests\Unit;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Commands\TestCommand;
use SoloTerm\Solo\Manager;

class TestCommandTest extends TestCase
{
    #[Test]
    public function test_command_automatically_sets_testing_environment(): void
    {
        $command = new TestCommand('Tests', 'php artisan test');

        $this->assertSame('testing', $command->getEnvironment()['APP_ENV']);
    }

    #[Test]
    public function artisan_factory_creates_lazy_command_with_testing_env(): void
    {
        $command = TestCommand::artisan();

        $this->assertInstanceOf(TestCommand::class, $command);
        $this->assertSame('testing', $command->getEnvironment()['APP_ENV']);
        $this->assertFalse($command->autostart);
        $this->assertStringContainsString('php artisan test', $command->command);
    }

    #[Test]
    public function pest_factory_creates_lazy_command_with_testing_env(): void
    {
        $command = TestCommand::pest();

        $this->assertInstanceOf(TestCommand::class, $command);
        $this->assertSame('testing', $command->getEnvironment()['APP_ENV']);
        $this->assertFalse($command->autostart);
        $this->assertStringContainsString('pest', $command->command);
    }

    #[Test]
    public function phpunit_factory_creates_lazy_command_with_testing_env(): void
    {
        $command = TestCommand::phpunit();

        $this->assertInstanceOf(TestCommand::class, $command);
        $this->assertSame('testing', $command->getEnvironment()['APP_ENV']);
        $this->assertFalse($command->autostart);
        $this->assertStringContainsString('phpunit', $command->command);
    }

    #[Test]
    public function artisan_factory_accepts_custom_args(): void
    {
        $command = TestCommand::artisan('--filter=UserTest');

        $this->assertStringContainsString('--filter=UserTest', $command->command);
    }

    #[Test]
    #[DataProvider('commandPatternProvider')]
    public function looks_like_test_command_detects_test_commands(string $command, bool $expected): void
    {
        $this->assertSame($expected, TestCommand::looksLikeTestCommand($command));
    }

    public static function commandPatternProvider(): array
    {
        return [
            'artisan test' => ['php artisan test', true],
            'artisan test with args' => ['php artisan test --filter=UserTest', true],
            'artisan test with colors' => ['php artisan test --colors=always', true],
            'pest command' => ['./vendor/bin/pest', true],
            'pest with args' => ['./vendor/bin/pest --filter=UserTest', true],
            'phpunit command' => ['./vendor/bin/phpunit', true],
            'phpunit with args' => ['./vendor/bin/phpunit --testsuite=unit', true],
            'vite command' => ['npm run dev', false],
            'queue work' => ['php artisan queue:work', false],
            'serve command' => ['php artisan serve', false],
            'tail command' => ['tail -f logs/laravel.log', false],
            'artisan in different context' => ['php artisan make:test', false],
        ];
    }

    #[Test]
    public function regular_command_does_not_have_testing_env_by_default(): void
    {
        $command = new Command('Tests', 'php artisan test');

        $this->assertEmpty($command->getEnvironment());
    }

    #[Test]
    public function regular_command_with_env_has_testing_env(): void
    {
        $command = Command::from('php artisan test')->withEnv(['APP_ENV' => 'testing']);

        $this->assertSame('testing', $command->getEnvironment()['APP_ENV']);
    }

    #[Test]
    public function manager_blocks_test_command_without_testing_env(): void
    {
        config()->set('solo.commands', []);
        $manager = new Manager;
        $manager->addCommand('php artisan test', 'Tests');

        $commands = $manager->commands();
        $testCommand = end($commands);

        $this->assertTrue($testCommand->isBlocked());
        $this->assertStringContainsString('wipe your local database', $testCommand->getBlockedReason());
        $this->assertStringContainsString('TestCommand::artisan()', $testCommand->getBlockedReason());
    }

    #[Test]
    public function manager_does_not_block_when_test_command_uses_test_command_class(): void
    {
        config()->set('solo.commands', []);
        $manager = new Manager;
        $manager->addCommand(TestCommand::artisan(), 'Tests');

        $commands = $manager->commands();
        $testCommand = end($commands);

        $this->assertFalse($testCommand->isBlocked());
    }

    #[Test]
    public function manager_does_not_block_when_test_command_has_env_set(): void
    {
        config()->set('solo.commands', []);
        $manager = new Manager;
        $manager->addCommand(
            Command::from('php artisan test')->withEnv(['APP_ENV' => 'testing']),
            'Tests'
        );

        $commands = $manager->commands();
        $testCommand = end($commands);

        $this->assertFalse($testCommand->isBlocked());
    }

    #[Test]
    public function manager_does_not_block_non_test_commands(): void
    {
        config()->set('solo.commands', []);
        $manager = new Manager;
        $manager->addCommand('npm run dev', 'Vite');
        $manager->addCommand('php artisan queue:work', 'Queue');

        $commands = $manager->commands();

        foreach ($commands as $command) {
            $this->assertFalse($command->isBlocked());
        }
    }

    #[Test]
    public function blocked_command_does_not_autostart(): void
    {
        $command = new Command('Tests', 'php artisan test');
        $command->block('Test reason');

        $this->assertFalse($command->autostart);
    }
}
