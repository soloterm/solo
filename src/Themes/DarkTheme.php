<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Themes;

class DarkTheme extends LightTheme
{
    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */
    public function tabFocused(string $text): string
    {
        return $this->bgWhite($this->black($text));
    }

    public function tabBlurred(string $text): string
    {
        return $this->dim($text);
    }

    public function tabStopped(string $text): string
    {
        $text = trim($text);

        return $this->red('•') . $this->dim($text . ' ');
    }

    public function tabRunning(string $text): string
    {
        $text = trim($text);

        return $this->green('•') . $this->dim($text . ' ');
    }

    public function tabPaused(string $text): string
    {
        $text = trim($text);

        return $this->yellow('•') . $this->dim($text . ' ');
    }

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    */
    public function logsPaused(string $text): string
    {
        return $this->yellow($text);
    }

    /*
    |--------------------------------------------------------------------------
    | Process
    |--------------------------------------------------------------------------
    */
    public function processStopped(string $text): string
    {
        return $this->red($text);
    }
}
