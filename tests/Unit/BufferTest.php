<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Support\Buffer;

class BufferTest extends Base
{
    #[Test]
    public function basic_buffer()
    {
        $buffer = new Buffer(usesStrings: true, max: 5);

        for ($i = 0; $i < 10; $i++) {
            $buffer[$i] = 'a';
        }

        // There's a 95% chance that `trim` does nothing, so we'll
        // call it 100 times just to make sure it worked.
        for ($i = 0; $i < 100; $i++) {
            $buffer->trim();
        }

        $this->assertEquals([
            0 => '',
            1 => '',
            2 => '',
            3 => '',
            4 => '',
            5 => 'a',
            6 => 'a',
            7 => 'a',
            8 => 'a',
            9 => 'a',
        ], $buffer->getBuffer());

    }
}
