<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Hotkeys;

use Illuminate\Support\Arr;
use Laravel\Prompts\Key;

class KeycodeMap
{
    /**
     * @var array<string,string>
     */
    public static $custom = [
        //
    ];

    public static function toDisplay($code)
    {
        return Arr::get([...static::map(), ...static::$custom], $code, $code);
    }

    public static function map()
    {
        // https://gist.github.com/GLMeece/6a2b71c57df228e5a4a35e4b92b0992f
        return [
            Key::UP => '↑',
            Key::UP_ARROW => '↑',
            Key::SHIFT_UP => '⇧↑',

            Key::DOWN => '↓',
            Key::DOWN_ARROW => '↓',
            Key::SHIFT_DOWN => '⇧↓',

            Key::RIGHT => '→',
            Key::RIGHT_ARROW => '→',

            Key::LEFT => '←',
            Key::LEFT_ARROW => '←',

            Key::ESCAPE => '⎋',
            Key::DELETE => '⌦',
            Key::BACKSPACE => '⌫',
            Key::ENTER => '↩',
            Key::SPACE => '␣',
            Key::TAB => '⇧⇥',
            Key::SHIFT_TAB => '⇧⇥',

            ...array_fill_keys(Key::HOME, '⇱'),
            ...array_fill_keys(Key::END, '⇲'),

            "\x18" => '⌃x',
            Key::CTRL_C => '⌃c',
            Key::CTRL_P => '⌃p',
            Key::CTRL_N => '⌃n',
            Key::CTRL_F => '⌃f',
            Key::CTRL_B => '⌃b',
            Key::CTRL_H => '⌃h',
            Key::CTRL_A => '⌃a',
            Key::CTRL_D => '⌃d',
            Key::CTRL_E => '⌃e',
            Key::CTRL_U => '⌃u',
        ];
    }
}
