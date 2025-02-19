<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Hotkeys;

use Laravel\Prompts\Key;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Contracts\HotkeyProvider;

class DefaultHotkeys implements HotkeyProvider
{
    /**
     * @return array<Hotkey>
     */
    public static function keys(): array
    {
        return array_values(static::keymap());
    }

    /**
     * @return array<string, Hotkey>
     */
    public static function keymap()
    {
        // The array keys have no meaning beyond providing a way
        // to directly address hotkeys. This is useful in the
        // VimKeys class to remap the navigation keys.
        return [
            'clear' => Hotkey::make('c', KeyHandler::Clear)
                ->label('Clear'),

            'pause' => Hotkey::make('p', KeyHandler::Pause)
                ->label('Pause ')
                ->visible(fn(Command $command) => !$command->paused),

            'follow' => Hotkey::make('f', KeyHandler::Follow)
                ->label('Follow')
                ->visible(fn(Command $command) => $command->paused),

            'start_stop' => Hotkey::make('s', KeyHandler::StartStop)
                ->label(fn(Command $command) => $command->processRunning() ? 'Stop ' : 'Start'),

            'restart' => Hotkey::make('r', KeyHandler::Restart)
                ->label('Restart'),

            'quit' => Hotkey::make(['q', Key::CTRL_C], KeyHandler::Quit)
                ->label('Quit'),

            // This is a no-op, display only key. The real scroll handlers are
            // right below, but they're invisible. We do this to save space.
            'prev_next' => Hotkey::make()
                ->keyDisplay('←/→')
                ->label('Prev/Next'),

            'previous_tab' => Hotkey::make([Key::LEFT, Key::LEFT_ARROW], KeyHandler::PreviousTab)
                ->label('Previous')
                ->invisible(),

            'next_tab' => Hotkey::make([Key::RIGHT, Key::RIGHT_ARROW], KeyHandler::NextTab)
                ->label('Next')
                ->invisible(),

            // This is a no-op, display only key. The real scroll handlers are
            // right below, but they're invisible. We do this to save space.
            'scroll' => Hotkey::make()
                ->keyDisplay('↑↓')
                ->label('Scroll'),

            'scroll_up' => Hotkey::make([Key::UP, Key::UP_ARROW], KeyHandler::ScrollUp)
                ->label('Scroll up')
                ->invisible(),

            'scroll_down' => Hotkey::make([Key::DOWN, Key::DOWN_ARROW], KeyHandler::ScrollDown)
                ->label('Scroll down')
                ->invisible(),

            'page_up' => Hotkey::make([Key::SHIFT_UP, Key::PAGE_UP], KeyHandler::PageUp)
                ->label('Page up')
                ->invisible(),

            'page_down' => Hotkey::make([Key::SHIFT_DOWN, KEY::PAGE_DOWN], KeyHandler::PageDown)
                ->label('Page down')
                ->invisible(),

            // @TODO once we drop support for Laravel 10, we can bump laravel/prompts to 0.3.3 and then use Key::HOME
            'home' => Hotkey::make(["\e[1~", "\eOH", "\e[H", "\e[7~"], KeyHandler::Home)
                ->label('Home')
                ->invisible(),

            // @TODO once we drop support for Laravel 10, we can bump laravel/prompts to 0.3.3 and then use Key::END
            'end' => Hotkey::make(["\e[4~", "\eOF", "\e[F", "\e[8~"], KeyHandler::End)
                ->label('End')
                ->invisible(),

            // 'dd' => Hotkey::make('d', KeyHandler::DD)
            //     ->label('DD')
            //     ->invisible(),

            // 'command_palette' => Hotkey::make('+', KeyHandler::ShowCommandChooser)
            //     ->label('Commands'),

            'tab_picker' => Hotkey::make('g', KeyHandler::ShowTabPicker)
                ->label('Go'),

            // 'help' => Hotkey::make('?', KeyHandler::ShowHelp)
            //     ->label('Help'),
        ];

    }
}
