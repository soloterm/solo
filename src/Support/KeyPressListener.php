<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

class KeyPressListener extends \Chewie\Input\KeyPressListener
{
    public function clear(): static
    {
        $this->regular = [];
        $this->escape = [];

        return $this->clearExisting();
    }
}
