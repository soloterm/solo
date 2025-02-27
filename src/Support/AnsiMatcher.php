<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

class AnsiMatcher
{
    public static function split(string $content)
    {
        $parts = preg_split(
            static::regex(), $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        return array_map(function ($part) {
            return str_starts_with($part, "\e") ? new AnsiMatch($part) : $part;

        }, $parts);
    }

    /**
     * @link https://raw.githubusercontent.com/chalk/ansi-regex/refs/heads/main/fixtures/ansi-codes.js
     */
    public static function regex(): string
    {
        return <<<EOT
/(
    \\x1B
    (?:
        [ABCDEHIJKMNOSTZ=><12su78c]
        |
        \\#[34568]
        |
        \\([AB0-2]
        |
        \\)[AB0-2]
        |
        \\[[0-9;?]*[@-~]
        |
        [0356]n
        |
        \\].+?(?:\x07|\x1B\x5C|\x9C)  # Valid string terminator sequences are BEL, ESC\, and 0x9c
    )
)/x
EOT;
    }
}
