<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Support\BaseConverter;

class BaseConverterTest extends Base
{
    #[Test]
    public function it_works(): void
    {
        for ($i = 0; $i < 100_000; $i++) {
            $encoded = BaseConverter::toString($i);
            $decoded = BaseConverter::toInt($encoded);

            $this->assertEquals($decoded, $i);
        }
    }
}
