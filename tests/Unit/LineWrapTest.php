<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use Laravel\Prompts\Concerns\Colors;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;

class LineWrapTest extends Base
{
    use Colors;

    #[Test]
    public function line_wrap(): void
    {
        $command = new Command;

        $wrapped = $command->wrapLine('123456789', 5);

        $this->assertEquals(['12345', '6789'], $wrapped);
    }

    #[Test]
    public function line_wrap_continuation(): void
    {
        $command = new Command;

        $wrapped = $command->wrapLine('123456789', 5, 3);

        $this->assertEquals(['12345', '   67', '   89'], $wrapped);
    }
}
