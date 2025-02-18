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
        return ' ' . $this->strikethrough(trim($text)) . ' ';
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
    | Text
    |--------------------------------------------------------------------------
    */
    public function invisible(string $text): string
    {
        // Not all terminals support invisible mode, so we'll make
        // the text black and invisible, for the best odds.
        return "\e[8m" . $this->black($text) . "\e[28m";
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
