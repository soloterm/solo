<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Contracts;

use SoloTerm\Solo\Hotkeys\Hotkey;

interface HotkeyProvider
{
    /**
     * @return array<Hotkey>
     */
    public static function keys(): array;
}
