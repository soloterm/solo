<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Hotkeys;

use Laravel\Prompts\Key;

class VimHotkeys extends DefaultHotkeys
{
    /**
     * @return array<string, Hotkey>
     */
    public static function keymap(): array
    {
        return static::remap(parent::keymap());
    }

    /**
     * In case you want VimKeys in addition to something else, you
     * can just use this function from your HotkeyProvider.
     */
    public static function remap($map): array
    {
        $map['prev_next']->keyDisplay('h/l');
        $map['scroll']->keyDisplay('j/k');

        $map['previous_tab']->remap('h');
        $map['next_tab']->remap('l');
        $map['scroll_up']->remap('k');
        $map['scroll_down']->remap('j');
        $map['page_up']->remap(Key::CTRL_U);
        $map['page_down']->remap(Key::CTRL_D);

        return $map;
    }
}
