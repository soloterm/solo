<?php

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;

class CommandResizeTest extends Base
{
    #[Test]
    public function resizing_preserves_existing_output(): void
    {
        $command = new Command;
        $command->setDimensions(80, 24);
        $command->addLine('before resize');

        $command->setDimensions(100, 30);

        $this->assertContains('before resize', $command->wrappedLines()->all());
    }

    #[Test]
    public function resizing_running_commands_propagates_the_new_pty_size(): void
    {
        $command = new class extends Command
        {
            public int $sizeUpdates = 0;

            public bool $running = false;

            public function processRunning(): bool
            {
                return $this->running;
            }

            public function sendSizeViaStty(): void
            {
                $this->sizeUpdates++;
            }
        };

        $command->setDimensions(80, 24);

        $this->assertSame(0, $command->sizeUpdates);

        $command->running = true;
        $command->setDimensions(100, 30);

        $this->assertSame(1, $command->sizeUpdates);
    }
}
