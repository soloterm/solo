<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
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
