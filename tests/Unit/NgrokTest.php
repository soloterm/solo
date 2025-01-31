<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Tests\Support\ComparesVisually;

use function Orchestra\Testbench\package_path;

class NgrokTest extends Base
{
    use ComparesVisually;

    #[Test]
    public function basic_ngrok()
    {
        $this->assertTerminalMatch(file_get_contents(package_path('tests/Fixtures/ngrok_1.txt')));
    }
}
