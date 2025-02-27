<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

readonly class AnsiMatch implements \Stringable
{
    public ?string $command;

    public ?string $params;

    public function __construct(public string $raw)
    {
        $pattern = <<<PATTERN
/
    ]
    (?<command_1>
        (10|11)
    )
    ;
    (?<params_1>
        \?
    )
|
    (?<command_2>
        [ABCDEHIJKMNOSTZ=><12su78c]
    )
|
\\[
    (?<params_3>
        [0-9;?]*
    )
    (?<command_3>
        [@-~]
    )
/x
PATTERN;

        preg_match($pattern, $this->raw, $matches);

        $command = null;
        $params = null;

        foreach ($matches as $name => $value) {
            if (str_starts_with($name, 'command_')) {
                $command = $value;
            }

            if (str_starts_with($name, 'params_')) {
                $params = $value;
            }
        }

        $this->command = $command;
        $this->params = $params;
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
