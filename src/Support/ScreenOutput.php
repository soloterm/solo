<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use SoloTerm\Screen\Screen;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScreenOutput implements OutputInterface
{
    protected int $verbosity = OutputInterface::VERBOSITY_NORMAL;

    protected bool $decorated = true;

    protected OutputFormatterInterface $formatter;

    public function __construct(public Screen $screen)
    {
        $this->formatter = new OutputFormatter;
        $this->formatter->setDecorated($this->decorated);
    }

    public function output()
    {
        return $this->screen->output();
    }

    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        $messages = $this->normalizeMessages($messages);
        $messages = $this->formatOutput($messages, $options);

        if ($newline) {
            $this->screen->writeln($messages);

            return;
        }

        $this->screen->write($messages);
    }

    public function writeln(iterable|string $messages, int $options = 0): void
    {
        $this->write($messages, true, $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function isSilent(): bool
    {
        return $this->verbosity === OutputInterface::VERBOSITY_SILENT;
    }

    public function isQuiet(): bool
    {
        return $this->verbosity === OutputInterface::VERBOSITY_QUIET;
    }

    public function isVerbose(): bool
    {
        return $this->verbosity >= OutputInterface::VERBOSITY_VERBOSE;
    }

    public function isVeryVerbose(): bool
    {
        return $this->verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE;
    }

    public function isDebug(): bool
    {
        return $this->verbosity >= OutputInterface::VERBOSITY_DEBUG;
    }

    public function setDecorated(bool $decorated): void
    {
        $this->decorated = $decorated;
        $this->formatter->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->formatter = $formatter;
        $this->formatter->setDecorated($this->decorated);
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->formatter;
    }

    protected function normalizeMessages(iterable|string $messages): string
    {
        if (is_iterable($messages)) {
            return implode('', [...$messages]);
        }

        return $messages;
    }

    protected function formatOutput(string $message, int $options): string
    {
        $outputType = $options & (OutputInterface::OUTPUT_NORMAL | OutputInterface::OUTPUT_RAW | OutputInterface::OUTPUT_PLAIN);

        $outputType = $outputType === 0 ? OutputInterface::OUTPUT_NORMAL : $outputType;

        return match ($outputType) {
            OutputInterface::OUTPUT_RAW => $message,
            OutputInterface::OUTPUT_PLAIN => strip_tags($this->formatter->format($message)),
            default => $this->formatter->format($message),
        };
    }
}
