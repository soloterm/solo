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
use SoloTerm\Solo\Support\AnsiAware;

class AnsiAwareTest extends Base
{
    use Colors;

    #[Test]
    public function ansi_mb_strlen(): void
    {
        $string = 'Hello ' . $this->green('World!');
        $this->assertEquals(12, AnsiAware::mb_strlen($string));

        $string = 'Hello ' . $this->bgRed($this->green('World!'));
        $this->assertEquals(12, AnsiAware::mb_strlen($string));

        $string = 'Hello ' . $this->bold($this->bgRed($this->green('World!')));
        $this->assertEquals(12, AnsiAware::mb_strlen($string));

        $string = 'Hello ' . $this->bold($this->bgRed($this->green('█orld!')));
        $this->assertEquals(12, AnsiAware::mb_strlen($string));
    }

    #[Test]
    public function ansi_substr_foreground(): void
    {
        $string = 'Hello ' . $this->green('World!');
        $substring = AnsiAware::substr($string, 3, 5);

        $this->assertEquals("lo \e[32mWo\e[39m\e[0m", $substring);
    }

    #[Test]
    public function ansi_substr_background(): void
    {
        $string = 'Hello ' . $this->bgGreen('World!');
        $substring = AnsiAware::substr($string, 3, 5);

        $this->assertEquals("lo \e[42mWo\e[49m\e[0m", $substring);
    }

    #[Test]
    public function ansi_substr_fore_and_background(): void
    {
        $string = 'Hello ' . $this->bgGreen($this->white('World!'));
        $substring = AnsiAware::substr($string, 3, 5);

        $this->assertEquals("lo \e[42m\e[37mWo\e[39m\e[49m\e[0m", $substring);
    }

    #[Test]
    public function ansi_substr_bold(): void
    {
        $string = 'Hello ' . $this->bold('World!');
        $substring = AnsiAware::substr($string, 3, 5);

        $this->assertEquals("lo \e[1mWo\e[22m\e[0m", $substring);
    }

    #[Test]
    public function ansi_substr_overlap(): void
    {
        $string = 'Hell' . $this->bgGreen('o ' . $this->blue('World!'));
        $substring = AnsiAware::substr($string, 3, 5);

        $this->assertEquals("l\e[42mo \e[34mWo\e[39m\e[49m\e[0m", $substring);
    }

    #[Test]
    public function ansi_substr_overlap_multibyte(): void
    {
        $string = 'Hell' . $this->bgGreen('█ ' . $this->blue('World!'));
        $substring = AnsiAware::substr($string, 3, 5);

        $this->assertEquals("l\e[42m█ \e[34mWo\e[39m\e[49m\e[0m", $substring);
    }

    #[Test]
    public function ansi_substr_no_style(): void
    {
        $string = 'Hello World';
        $substring = AnsiAware::substr($string, 3, 5);

        $this->assertEquals('lo Wo', $substring);
    }

    #[Test]
    public function it_wraps_a_basic_line(): void
    {
        $line = str_repeat('a', 10);
        $width = 5;

        $wrapped = AnsiAware::wordwrap(string: $line, width: $width, cut: true);

        $this->assertEquals(
            "aaaaa\naaaaa",
            $wrapped
        );
    }

    #[Test]
    public function it_wraps_an_ansi_line(): void
    {
        $line = $this->bgRed($this->green(str_repeat('a', 10)));
        $width = 5;

        $wrapped = AnsiAware::wordwrap(string: $line, width: $width, cut: true);

        $this->assertEquals(
            "\e[41m\e[32maaaaa\e[0m\n\e[41m\e[32maaaaa\e[39m\e[49m\e[0m",
            $wrapped
        );
    }
}
