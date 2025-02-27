<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScreenOutput implements OutputInterface
{
    public function __construct(public Screen $screen)
    {
        //
    }

    public function output()
    {
        return $this->screen->output();
    }

    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        if (is_iterable($messages)) {
            $messages = implode('', $messages);
        }

        $this->screen->write($messages);
    }

    public function writeln(iterable|string $messages, int $options = 0): void
    {
        if (is_iterable($messages)) {
            $messages = implode('', $messages);
        }

        // append the output to debug.txt
        file_put_contents('debug.txt', $messages, FILE_APPEND);

        $this->screen->writeln($messages);
    }

    public function setVerbosity(int $level): void
    {
        //
    }

    public function getVerbosity(): int
    {
        return 1;
    }

    public function isQuiet(): bool
    {
        return false;
    }

    public function isVerbose(): bool
    {
        return false;
    }

    public function isVeryVerbose(): bool
    {
        return false;
    }

    public function isDebug(): bool
    {
        return false;
    }

    public function setDecorated(bool $decorated): void
    {
        //
    }

    public function isDecorated(): bool
    {
        return true;
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        //
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return new OutputFormatter;
    }
}
