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

    #[Test]
    public function ansi_line_wrap_continuation_preserves_styles(): void
    {
        $command = new Command;

        $wrapped = $command->wrapLine($this->bgRed($this->green(str_repeat('a', 9))), 5, 3);

        $this->assertEquals([
            "\e[41m\e[32maaaaa\e[0m",
            "   \e[41m\e[32maa\e[0m",
            "   \e[41m\e[32maa\e[39m\e[49m\e[0m",
        ], $wrapped);
    }
}
