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
}
