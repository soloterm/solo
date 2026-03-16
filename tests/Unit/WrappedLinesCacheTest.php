<?php

namespace SoloTerm\Solo\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;

class WrappedLinesCacheTest extends Base
{
    #[Test]
    public function wrapped_lines_are_cached_until_the_screen_changes(): void
    {
        $command = new class extends Command
        {
            public int $modifyCalls = 0;

            protected function modifyWrappedLines(Collection $lines): Collection
            {
                $this->modifyCalls++;

                return $lines;
            }
        };

        $command->setDimensions(80, 24);
        $command->addLine('first line');

        $command->wrappedLines();
        $command->wrappedLines();

        $this->assertSame(1, $command->modifyCalls);

        $command->addLine('second line');
        $command->wrappedLines();

        $this->assertSame(2, $command->modifyCalls);
    }
}
