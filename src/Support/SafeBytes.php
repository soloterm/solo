<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

class SafeBytes
{
    public static int $backtrack = 10;

    public static function parse(string $string): array
    {
        $len = strlen($string);
        $split = min($len, static::$backtrack);

        $carry = substr($string, 0, $len - $split);
        $check = substr($string, $len - $split);

        $chars = mb_str_split($check, 1, 'UTF-8');

        $validFound = false;
        foreach ($chars as &$char) {
            $valid = static::isValidUtf8Character($char);

            // If it's a valid character we can just append it. The second condition is a little
            // more complex. There's a very real possibility we spliced a multibyte character
            // ourselves by using the substr function above. If we haven't yet found a
            // valid character, but there is some carry, then we spliced it.

            // Just append the garbage and move forward looking for a valid character. If
            // there is no carry, then that means that the entire string is invalid.
            if ($valid || (!$validFound && $carry)) {
                $carry .= $char;
                $char = null;
            }

            $validFound = $validFound || $valid;
        }

        return [$carry, implode('', array_filter($chars))];
    }

    public static function isValidUtf8Character(string $char): bool
    {
        $len = strlen($char);

        if ($len === 0) {
            // Empty string is not a valid character
            return false;
        }

        $byte1 = ord($char[0]);

        if ($byte1 <= 0x7F) {
            // 1-byte character (ASCII)
            return $len === 1;
        } elseif ($byte1 >= 0xC2 && $byte1 <= 0xDF) {
            // 2-byte character
            if ($len !== 2) {
                return false; // Incomplete character
            }
            $byte2 = ord($char[1]);

            return ($byte2 & 0xC0) === 0x80;
        } elseif ($byte1 >= 0xE0 && $byte1 <= 0xEF) {
            // 3-byte character
            if ($len !== 3) {
                return false; // Incomplete character
            }
            $byte2 = ord($char[1]);
            $byte3 = ord($char[2]);

            return (($byte2 & 0xC0) === 0x80) && (($byte3 & 0xC0) === 0x80);
        } elseif ($byte1 >= 0xF0 && $byte1 <= 0xF4) {
            // 4-byte character
            if ($len !== 4) {
                return false; // Incomplete character
            }
            $byte2 = ord($char[1]);
            $byte3 = ord($char[2]);
            $byte4 = ord($char[3]);

            return (($byte2 & 0xC0) === 0x80) &&
                (($byte3 & 0xC0) === 0x80) &&
                (($byte4 & 0xC0) === 0x80);
        } else {
            // Invalid first byte
            return false;
        }
    }
}
