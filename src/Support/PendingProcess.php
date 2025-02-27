<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Illuminate\Process\PendingProcess as BasePendingProcess;

class PendingProcess extends BasePendingProcess
{
    public bool $pty = false;

    public function pty(bool $pty = true): static
    {
        $this->pty = $pty;

        return $this;
    }

    // Not all versions of Laravel have this. Once we drop
    // Laravel 10 we can remove this shim.
    public function input($input)
    {
        $this->input = $input;

        return $this;
    }

    protected function toSymfonyProcess(array|string|null $command): \Symfony\Component\Process\Process
    {
        $process = parent::toSymfonyProcess($command);

        $process->setPty($this->pty);

        return $process;
    }
}
