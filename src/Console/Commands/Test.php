<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Laravel\SerializableClosure\Serializers\Signed;
use SoloTerm\Solo\Facades\Solo as SoloAlias;

class Test extends Solo
{
    protected $signature = 'solo:test {class} {provider}';

    protected $description = 'Run solo with an ad-hoc service provider';

    public function handle(): void
    {
        Signed::$signer = null;
        SoloAlias::clearCommands();

        $closure = unserialize($this->argument('provider'))->getClosure();

        call_user_func($closure);

        SoloAlias::loadCommands();

        parent::handle();
    }
}
