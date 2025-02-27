<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Facades;

use Illuminate\Support\Facades\Facade;
use SoloTerm\Solo\Manager;

class Solo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
