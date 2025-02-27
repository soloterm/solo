<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Commands\Command;

class AnsiWrapTest extends Base
{
    #[Test]
    public function weird_wrapping(): void
    {
        $trace = '#23 /Users/aaron/Code/solo/vendor/laravel/framework/src/Illuminate/Console/Command.php(181): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle)) this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap';

        $cmd = new Command;

        $wrapped = $cmd->wrapLine($trace, 190, 4);

        $this->assertEquals([
            "#23 /Users/aaron/Code/solo/vendor/laravel/framework/src/Illuminate/Console/Command.php(181): Symfony\Component\Console\Command\Command->run(Object(Symfony\Component\Console\Input\ArgvInput),",
            "    Object(Illuminate\Console\OutputStyle)) this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it ",
            '    should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it should hopefully wrap this is a very long string and it should hopefully w',
            '    rap this is a very long string and it should hopefully wrap'
        ], $wrapped);

    }
}
