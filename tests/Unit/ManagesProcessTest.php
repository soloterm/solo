<?php

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;

class ManagesProcessTest extends Base
{
    #[Test]
    public function commands_are_not_tokenized_when_screen_is_disabled(): void
    {
        config()->set('solo.use_screen', false);

        $command = new class(name: 'Tests', command: 'APP_ENV=testing php artisan test --filter="User Test"') extends Command
        {
            public function build(): array|string
            {
                $this->setDimensions(100, 40);

                return $this->buildCommandArray($this->screen);
            }
        };

        $this->assertSame(
            'APP_ENV=testing php artisan test --filter="User Test"',
            $command->build()
        );
    }

    #[Test]
    public function process_tracking_state_can_be_reset_without_running_a_process(): void
    {
        $command = new class(name: 'Demo', command: 'echo ok') extends Command
        {
            public function seedTrackingState(): void
            {
                $this->children = [101, 202];
                $this->screenWrapperChildren = [101 => true, 202 => false];
                $this->cachedPtyDevice = '/dev/pts/3';
                $this->cachedPtyDevicePid = 202;
                $this->partialBuffer = 'leftover';
                $this->hadOutputThisTick = true;
            }

            public function resetTrackingStateForTest(): void
            {
                $this->resetProcessTrackingState();
            }

            public function trackingState(): array
            {
                return [
                    'children' => $this->children,
                    'screenWrapperChildren' => $this->screenWrapperChildren,
                    'cachedPtyDevice' => $this->cachedPtyDevice,
                    'cachedPtyDevicePid' => $this->cachedPtyDevicePid,
                    'partialBuffer' => $this->partialBuffer,
                    'hadOutputThisTick' => $this->hadOutputThisTick,
                ];
            }
        };

        $command->seedTrackingState();
        $command->resetTrackingStateForTest();

        $this->assertSame([
            'children' => [],
            'screenWrapperChildren' => [],
            'cachedPtyDevice' => null,
            'cachedPtyDevicePid' => null,
            'partialBuffer' => '',
            'hadOutputThisTick' => false,
        ], $command->trackingState());
    }
}
