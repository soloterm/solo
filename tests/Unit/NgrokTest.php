<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Unit;

use Laravel\Prompts\Terminal;
use PHPUnit\Framework\Attributes\Test;
use SoloTerm\Solo\Tests\Support\ComparesVisually;

use function Orchestra\Testbench\package_path;

class NgrokTest extends Base
{
    use ComparesVisually;

    #[Test]
    public function basic_ngrok_1()
    {
        $this->assertTerminalMatch("abcdefg\e[1;2H🐛");
    }

    #[Test]
    public function basic_ngrok_2()
    {
        $this->assertTerminalMatch("abcdefg\e[1;2H❤️");
    }


    #[Test]
    public function basic_ngrok_3()
    {
        $width = $this->makeIdenticalScreen()->width;
        $full = str_repeat('-', $width);

        // 1 char, 3 bytes
        $this->assertTerminalMatch($full . "\e[1;5H🐛");
        // 2 chars, 6 bytes
        $this->assertTerminalMatch($full . "\e[1;5H❤️");
    }

    #[Test]
    public function basic_ngrok_4()
    {
        $width = $this->makeIdenticalScreen()->width;

        $emoji = '🐛';
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));

        $this->assertTerminalMatch($full . "\e[;5H aaron ");
    }

    #[Test]
    public function basic_ngrok_5()
    {
        $width = $this->makeIdenticalScreen()->width;

        $emoji = '❤️';
        $full = $emoji . str_repeat('-', $width - mb_strwidth($emoji, 'UTF-8'));

        $this->assertTerminalMatch($full . "\e[;5H aaron ");
    }

    #[Test]
    public function basic_ngrok_6()
    {
        $this->assertTerminalMatch("🐛asdf\e[;15H aaron ");
        $this->assertTerminalMatch("❤️asdf\e[;15H aaron ");
    }

    #[Test]
    public function basic_ngrok_7()
    {
        $this->assertTerminalMatch("❤️a\n..a");
    }

    #[Test]
    public function basic_ngrok_8()
    {
        $this->assertTerminalMatch("❤️a\e[2D.\n..");
    }

}
