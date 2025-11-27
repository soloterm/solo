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

    /**
     * Process a key that was already read from input.
     * This allows external code to handle the input reading
     * while still using the listener's key handling logic.
     */
    public function processKey(string $key): void
    {
        $this->handleKey($key);
    }
}
