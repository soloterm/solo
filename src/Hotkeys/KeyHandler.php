<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Hotkeys;

use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Popups\CommandPalette;
use SoloTerm\Solo\Popups\Help;
use SoloTerm\Solo\Popups\TabPicker;
use SoloTerm\Solo\Prompt\Dashboard;

enum KeyHandler
{
    // Logs
    case Clear;
    case Pause;
    case Follow;
    case ScrollUp;
    case ScrollDown;
    case PageUp;
    case PageDown;

    case Home;

    case End;

    // Processes
    case Start;
    case Stop;
    case StartStop;
    case Restart;
    case PreviousTab;
    case NextTab;
    case Interactive;

    // Application
    case Quit;
    case DD;
    case ShowCommandChooser;
    case ShowTabPicker;
    case ShowHelp;

    public function handler(): \Closure
    {
        return match ($this) {
            self::Clear => fn(Command $command) => $command->clear(),
            self::Pause => fn(Command $command) => $command->pause(),
            self::Follow => fn(Command $command) => $command->follow(),
            self::Restart => fn(Command $command) => $command->restart(),
            self::Stop => fn(Command $command) => $command->stop(),
            self::Start => fn(Command $command) => $command->start(),
            self::StartStop => fn(Command $command) => $command->toggle(),
            self::Quit => fn(Dashboard $prompt) => $prompt->quit(),
            self::PreviousTab => fn(Dashboard $prompt) => $prompt->previousTab(),
            self::NextTab => fn(Dashboard $prompt) => $prompt->nextTab(),
            self::ScrollUp => fn(Command $command) => $command->scrollUp(),
            self::ScrollDown => fn(Command $command) => $command->scrollDown(),
            self::PageUp => fn(Command $command) => $command->pageUp(),
            self::PageDown => fn(Command $command) => $command->pageDown(),
            self::Home => fn(Command $command) => $command->scrollToTop(),
            self::End => fn(Command $command) => $command->scrollToBottom(),
            self::Interactive => fn(Dashboard $prompt) => $prompt->enterInteractiveMode(),
            self::DD => fn(Command $command) => $command->dd(),
            self::ShowCommandChooser => fn(Dashboard $prompt) => $prompt->showPopup(new CommandPalette),
            self::ShowTabPicker => fn(Dashboard $prompt) => $prompt->showPopup(new TabPicker),
            self::ShowHelp => fn(Dashboard $prompt) => $prompt->showPopup(new Help),
        };
    }
}
