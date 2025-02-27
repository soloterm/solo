<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;

class CommandSerializationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->beforeApplicationDestroyed(function () {
            $this->artisan('config:clear');
        });

        parent::setUp();
    }

    #[Test]
    public function command_config_can_be_serialized_and_cached(): void
    {
        $this->artisan('config:clear');
        $filesystem = app(Filesystem::class);
        $config = $filesystem->getRequire(__DIR__ . '/../../workbench/config/solo.php');
        $cachedConfigPath = $this->app->getCachedConfigPath();

        $this->assertUnserializedConfig($config);
        $this->assertFileDoesNotExist($cachedConfigPath);

        $result = Artisan::call('config:cache');

        $this->assertEquals(0, $result);
        $this->assertFileExists($cachedConfigPath);
        $this->assertFileIsReadable($cachedConfigPath);

        $cachedConfig = $filesystem->getRequire($cachedConfigPath)['solo'];

        $this->assertEquals($config['commands'], $cachedConfig['commands']);
        $this->assertSerializedConfig($filesystem->get($cachedConfigPath));

        $this->artisan('config:clear');
        $this->assertFileDoesNotExist($cachedConfigPath, 'Config cache file still exists');
    }

    /**
     * Assert that the serialized string has the expected format
     */
    private function assertSerializedConfig(string $serialized): void
    {
        $this->assertNotEmpty($serialized);
        $this->assertStringContainsString('solo', $serialized);
        $this->assertStringContainsString('commands', $serialized);

        $this->assertStringContainsString(Command::class, $serialized);
    }

    /**
     * Assert that the config line contains command instances to be serialised
     */
    protected function assertUnserializedConfig($config): void
    {
        $this->assertIsArray($config);
        $this->assertArrayHasKey('commands', $config);
        $this->assertNotEmpty($config['commands']);

        foreach ($config['commands'] as $configCommand) {
            $this->assertUnserializedCommands($configCommand);
        }
    }

    /**
     * Recursively check that there is command instances
     */
    private function assertUnserializedCommands($command): void
    {
        if ($command instanceof Command) {
            $this->assertInstanceOf(Command::class, $command);
        } elseif (is_array($command)) {
            foreach ($command as $item) {
                if ($item instanceof Command) {
                    $this->assertInstanceOf(Command::class, $item);
                }
            }
        }
    }
}
