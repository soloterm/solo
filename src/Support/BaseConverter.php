<?php

namespace SoloTerm\Solo\Support;

use InvalidArgumentException;

class BaseConverter
{
    public const ALPHABET = '!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';

    public static function toString(int $integer): string
    {
        if ($integer < 0) {
            throw new InvalidArgumentException('Invalid input. Integer must be non-negative.');
        }

        $base = strlen(self::ALPHABET);

        if ($integer === 0) {
            return self::ALPHABET[0];
        }

        $result = '';

        while ($integer > 0) {
            $remainder = $integer % $base;
            $result = self::ALPHABET[$remainder] . $result;
            $integer = intdiv($integer, $base);
        }

        return $result;
    }

    public static function toInt(string $string): int
    {
        $base = strlen(self::ALPHABET);
        $length = strlen($string);
        $integer = 0;

        for ($i = 0; $i < $length; $i++) {
            $digit = strpos(self::ALPHABET, $string[$i]);

            if ($digit === false) {
                throw new InvalidArgumentException(sprintf('Invalid character "%s" in input.', $string[$i]));
            }

            $integer = $integer * $base + $digit;
        }

        return $integer;
    }
}
