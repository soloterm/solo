<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Prompt;

use Chewie\Concerns\Aligns;
use Chewie\Concerns\DrawsHotkeys;
use Chewie\Output\Util;
use Illuminate\Support\Collection;
use Laravel\Prompts\Themes\Default\Concerns\DrawsScrollbars;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use Laravel\Prompts\Themes\Default\Renderer as PromptsRenderer;
use SoloTerm\Screen\Screen;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Contracts\Theme;
use SoloTerm\Solo\Facades\Solo;
use SoloTerm\Solo\Hotkeys\Hotkey;
use SoloTerm\Solo\Popups\Popup;
use SoloTerm\Solo\Support\AnsiAware;

class Renderer extends PromptsRenderer
{
    use Aligns, DrawsHotkeys, DrawsScrollbars, InteractsWithStrings;

    public Theme $theme;

    public Dashboard $dashboard;

    public Command $currentCommand;

    public int $width;

    public int $height;

    /**
     * Content lines rendered inside the scroll pane.
     *
     * @var array<int, string>
     */
    protected array $visibleContent = [];

    /**
     * The Screen instance used for rendering.
     */
    protected ?Screen $screen = null;

    /**
     * Parsed box-character maps keyed by the source box string.
     *
     * @var array<string, array<string, string>>
     */
    protected array $boxCache = [];

    /**
     * Precomputed marquee frame-start positions keyed by "length:width".
     *
     * @var array<string, array<int, int>>
     */
    protected array $marqueeStartsCache = [];

    protected string $scrollbarTrack = '';

    protected string $scrollbarHandle = '';

    protected string $leftBorder = '';

    protected string $rightBorder = '';

    public function setup(Dashboard $dashboard): void
    {
        $this->output = '';
        $this->screen = null;
        $this->clearHotkeys();
        $this->dashboard = $dashboard;
        $this->theme = Solo::theme();
        $this->currentCommand = $dashboard->currentCommand();
        $this->width = $dashboard->width;
        $this->height = $dashboard->height;
        $this->scrollbarTrack = $this->gray('│');
        $this->scrollbarHandle = $this->cyan('┃');
        $this->leftBorder = $this->coloredBox('│');
        $this->rightBorder = $this->reset($this->leftBorder);
    }

    /**
     * Render the dashboard and retain the composed Screen for diff-based output.
     */
    public function __invoke(Dashboard $dashboard): mixed
    {
        $this->setup($dashboard);

        $this->renderTabs();
        $this->renderProcessState();
        $this->renderContentPane();
        $this->renderHotkeys();

        $this->screen = new Screen($this->width, $this->height);
        $this->screen->write("\e[0m");
        // Match __toString() semantics so the virtual screen does not scroll
        // and drop the first row when the frame ends with a trailing newline.
        $this->screen->write((string) $this);
        // Move home then down two lines to get to the content pane.
        $this->screen->write("\e[H\e[0m\e[2B");

        // Write the visible content into the pane, padding with two spaces.
        foreach ($this->visibleContent as $line) {
            $this->screen->writeln("\e[2C" . $line);
        }

        if ($this->dashboard->popup) {
            $this->screen = $this->accountForPopup($this->screen, $this->dashboard->popup);
        }

        $this->output = $this->screen->output();

        return $this;
    }

    /**
     * Get the Screen instance used for rendering.
     *
     * This allows differential rendering by comparing Screens frame-to-frame.
     */
    public function getScreen(): ?Screen
    {
        return $this->screen;
    }

    public function __toString(): string
    {
        // We're running the output right up to the edge,
        // so we can't afford phantom newlines.
        return rtrim($this->output, "\n");
    }

    protected function line(string $message): PromptsRenderer
    {
        $message = $this->pad($message, $this->width);

        return parent::line($message);
    }

    protected function accountForPopup(Screen $screen, ?Popup $popup = null): Screen
    {
        if (is_null($popup)) {
            return $screen;
        }

        $plain = new Screen($this->width, $this->height);

        // Write the current output into a new screen, but remove all
        // the ANSI codes and forcibly set it to dim and gray.
        $plain->write("\e[0;2;38;5;251m" . AnsiAware::plain($screen->output()));

        $screen = $plain;

        // @TODO make the width configurable / determined by the popup itself.
        $offset = max(0, (int) floor(($this->width - 80) / 2));
        $screen->write($popup->render($offset, 2));

        return $screen;
    }

    protected function renderTabs(): void
    {
        $tabs = collect($this->dashboard->commands)->values()->map(fn(Command $command, int $index) => [
            'command' => $command,
            'display' => " $command->name ",
            // Fallback to selectedCommand so the focused tab stays visible
            // even if focus state drifts on command instances.
            'focused' => $command->isFocused() || $index === $this->dashboard->selectedCommand,
        ]);

        // Find the index of the focused tab.
        $focused = $tabs->pluck('focused')->search(value: true, strict: true);

        if ($focused === false) {
            $focused = max(0, min($this->dashboard->selectedCommand, max(0, $tabs->count() - 1)));
        }

        // Pass it through to figure out what all the visible
        // tabs should be, based on our available width.
        [$start, $end] = $this->calculateVisibleTabs($tabs, $focused, $this->width);

        // Slice out the visible tabs based on the indexes we
        // received back, then add the styling.
        $selectedTabs = $tabs
            ->slice($start, $end - $start + 1)
            ->map(fn($tab) => $this->styleTab($tab['command'], $tab['display'], $tab['focused']))
            ->implode(' ');

        // Some tabs on the left side weren't able to be
        // rendered, so we need to show an indicator.
        if ($start > 0) {
            // Allow for 2 parens, an arrow, a space, and 1-2 digits. If there is only
            // one digit we pad the full string to 6 characters to prevent jumpiness
            // and so we can reliably use 6 in the calculateVisibleTabs method.
            $more = $this->theme->tabMore(
                $this->mbStrPad("(← $start)", 6, ' ', STR_PAD_RIGHT)
            );

            $selectedTabs = "{$more}{$selectedTabs}";
        }

        // We're missing some tabs on the right side, so show an indicator.
        if ($end < $tabs->count() - 1) {
            // Same deal as above about padding to 6.
            $more = $this->mbStrPad('(' . ($tabs->count() - 1 - $end) . ' →)', 6, ' ', STR_PAD_LEFT);

            // How much space do we have left?
            $remaining = $this->width - mb_strlen(Util::stripEscapeSequences($selectedTabs)) - mb_strlen($more);

            // If we have enough to do something interesting, then we
            // grab the next tab and truncate it. (5 is arbitrary.)
            if ($remaining > 5) {
                $peek = $tabs->get($end + 1);
                $truncated = $this->truncate($peek['display'], $remaining - 1) . ' ';

                $peek = $this->styleTab($peek['command'], $truncated, $peek['focused']);
            } else {
                // Otherwise just show some spaces
                $peek = str_repeat(' ', max($remaining, 0));
            }

            $selectedTabs = $selectedTabs . $peek . $this->theme->tabMore($more);
        }

        $this->line($selectedTabs);
    }

    protected function styleTab(Command $command, string $name, ?bool $focused = null): string
    {
        $state = $command->processRunning()
            ? ($command->paused ? 'paused' : 'running')
            : 'stopped';

        $isFocused = $focused ?? $command->isFocused();

        return $isFocused ? $this->theme->tabFocused($name, $state) : $this->theme->tabBlurred($name, $state);
    }

    protected function renderProcessState(): void
    {
        $state = $this->currentCommand->processRunning()
            ? $this->theme->processRunning(' Running: ')
            : $this->theme->processStopped(' Stopped: ');

        $command = $this->marquee(
            $this->dim($this->currentCommand->command),
            $this->width - AnsiAware::mb_strlen($state),
            $this->dashboard->frames->current(buffer: 6)
        );

        $this->line($state . $command);
    }

    protected function marquee(string $string, int $width, int $frame): string
    {
        $length = AnsiAware::mb_strlen($string);

        if ($length <= $width) {
            return $string;
        }

        // Maximum starting position
        $maxPos = $length - $width;

        $cacheKey = $length . ':' . $width;
        if (!isset($this->marqueeStartsCache[$cacheKey])) {
            // Define the sequence of positions for the marquee effect.
            $this->marqueeStartsCache[$cacheKey] = array_merge(
                [0, 0],
                range(1, $maxPos),
                [$maxPos, $maxPos],
                range($maxPos - 1, 0, -1),
                [0]
            );
        }

        $starts = $this->marqueeStartsCache[$cacheKey];

        $totalFrames = count($starts);

        // Calculate the current index in the positions array
        $index = $frame % $totalFrames;
        $start = $starts[$index];

        return AnsiAware::substr($string, $start, $width);
    }

    protected function renderContentPane(): void
    {
        $allowedLines = $this->currentCommand->scrollPaneHeight();
        $wrappedLines = $this->currentCommand->wrappedLines();
        $start = $this->currentCommand->scrollIndex;
        $visibleContent = $wrappedLines->slice($start, $allowedLines)->values()->all();

        $this->visibleContent = $visibleContent;

        // Replace all content with spaces. We add the content
        // into the pane separately in the __invoke method.
        $visible = collect($visibleContent)
            ->map(fn(string $line) => str_repeat(' ', mb_strlen(AnsiAware::plain($line), 'UTF-8')))
            ->values()
            ->all();

        $totalLines = $wrappedLines->count();

        // Add one since we're showing what lines they're viewing.
        // There's no such thing as a zeroth line.
        $this->renderBoxTop($start + 1, $start + count($visible), $totalLines);

        // Try to scroll the content, which may or may not have an
        // effect, depending on how much content there is.
        $scrolled = $this->scrollbar(
            // Subtract 1 for the left box border and 1 for the space after it.
            $visible, $start, $allowedLines, $totalLines, max(1, $this->width - 2)
        );

        // If this conditional is true then it means that there wasn't
        // enough content to scroll, so we have to pretend by padding.
        if ($scrolled === $visible) {
            $scrolled = $this->padScrolledContent($scrolled, $allowedLines);
        }

        foreach ($scrolled as $line) {
            // Remove the gray scrollbar and replace it with
            // our own that matches the theme's box.
            $line = $this->replaceLastOccurrence($line, $this->scrollbarTrack, $this->rightBorder);

            // Replace the handle with the user's preferred handle.
            $line = $this->replaceLastOccurrence($line, $this->scrollbarHandle, $this->theme->boxHandle());

            // Only need to add the left side, because the
            // right side is made up of the scrollbar.
            $this->line($this->leftBorder . ' ' . $line);
        }

        // Box bottom border
        $this->line(
            $this->colorBox(
                $this->box('╰') . str_repeat($this->box('─'), max(0, $this->width - 2)) . $this->box('╯')
            )
        );
    }

    protected function renderBoxTop(int $start, int $stop, int $total): void
    {
        // Otherwise it'd read "Viewing [1-0] of 0", which is insane.
        if ($total === 0) {
            $start = 0;
        }

        $interactive = $this->currentCommand->isInteractive() ? ' Interactive ' : '';

        $count = "Viewing [$start-$stop] of $total";
        $state = $this->currentCommand->paused ? '(Paused)' : '(Live)';

        $stateTreatment = $this->currentCommand->paused ? 'logsPaused' : 'logsLive';

        $filler = max(0,
            $this->width
            // 5 hardcoded border pieces, 3 hardcoded spaces
            - 5 - 3
            - mb_strlen($state) - mb_strlen($count) - mb_strlen($interactive)
        );

        $border = ''
            . $this->coloredBox('╭')
            . $this->coloredBox('─')
            . $this->bgCyan($interactive)
            . $this->coloredBox('─')
            . $this->colorBox(str_repeat($this->box('─'), $filler))
            . ' '
            . $this->theme->dim($count)
            . ' '
            . $this->theme->{$stateTreatment}($state)
            . ' '
            . $this->coloredBox('─')
            . $this->coloredBox('╮');

        $this->line($border);
    }

    protected function renderHotkeys(): void
    {
        $localHotkeys = $this->currentCommand->allHotkeys();

        if (count($localHotkeys)) {
            $this->renderHotkeySubset($localHotkeys);
        }

        $this->clearHotkeys();

        $globalHotkeys = $this->currentCommand->isInteractive() ? [] : Solo::hotkeys();

        $this->renderHotkeySubset($globalHotkeys);
    }

    protected function box(string $part): string
    {
        $box = $this->currentCommand->isInteractive() ? $this->theme->boxInteractive() : $this->theme->box();

        if (!isset($this->boxCache[$box])) {
            $this->boxCache[$box] = $this->buildBoxMap($box);
        }

        // Example box
        // ╭─┬─╮
        // ├─┼─┤
        // │ │ │
        // ╰─┴─╯

        return $this->boxCache[$box][$part] ?? $part;
    }

    protected function coloredBox(string $piece): string
    {
        return $this->colorBox($this->box($piece));
    }

    protected function colorBox(string $text): string
    {
        return $this->currentCommand->isInteractive()
            ? $this->theme->boxBorderInteractive($text)
            : $this->theme->boxBorder($text);
    }

    /**
     * @param  array<int, string>  $scrolled
     * @return array<int, string>
     */
    protected function padScrolledContent(array $scrolled, int $allowedLines): array
    {
        // Fill out the collection with enough lines to fill the screen.
        while (count($scrolled) < $allowedLines) {
            $scrolled[] = '';
        }

        // Pad every line all the way to the right and add a pretend scrollbar.
        return array_map(function ($line) {
            // We use gray('│') because that's what the scrollbar
            // method does. We'll customize it further down.
            // (3 = 1 left bar + 1 space + 1 right bar.)
            return $this->pad($line, max(0, $this->width - 3)) . $this->scrollbarTrack;
        }, $scrolled);
    }

    /**
     * @param  Collection<int, array{command: Command, display: string, focused: bool}>  $tabs
     * @return array{0: int, 1: int}
     */
    protected function calculateVisibleTabs(
        Collection $tabs,
        int $focused,
        int $maxWidth,
        bool $moreOnLeft = false,
        bool $moreOnRight = false
    ): array {
        // Start with just the focused tab.
        $selectedTabs = collect($tabs->slice($focused, 1));

        $left = $focused - 1;
        $right = $focused + 1;
        $totalTabs = $tabs->count();

        // If we have tabs we can't show, then we need to leave some space
        // for the little (6 →) indicators that show how many tabs are
        // hidden. Those indicators are guaranteed to be 6 characters
        // long, so we just hardcode those widths here.
        $adjustedWidth = $maxWidth - ($moreOnLeft ? 6 : 0) - ($moreOnRight ? 6 : 0);

        while (true) {
            $currentLength = mb_strlen($selectedTabs->pluck('display')->implode(' '));

            // There are tabs we can't show, and we haven't yet taken that into consideration.
            if ($left > 0 && !$moreOnLeft) {
                // Just completely bail out and start over. Otherwise
                // we could overrun our allowed width.
                return $this->calculateVisibleTabs(
                    $tabs, $focused, $maxWidth, moreOnLeft: true, moreOnRight: $moreOnRight
                );
            }

            if ($right < $tabs->count() && !$moreOnRight) {
                return $this->calculateVisibleTabs(
                    $tabs, $focused, $maxWidth, moreOnLeft: $moreOnLeft, moreOnRight: true
                );
            }

            $canAddLeft = $canAddRight = false;

            // Check if we can add the tab to the left
            if ($left >= 0) {
                $leftLength = mb_strlen($tabs[$left]['display']);
                // Add one to account for the space during implosion.
                if ($currentLength + $leftLength + 1 <= $adjustedWidth) {
                    $canAddLeft = true;
                }
            }

            // Check if we can add the tab to the right
            if ($right < $totalTabs) {
                $rightLength = mb_strlen($tabs[$right]['display']);
                // Add one to account for the space during implosion.
                if ($currentLength + $rightLength + 1 <= $adjustedWidth) {
                    $canAddRight = true;
                }
            }

            // Break the loop if we can't add any more tabs
            if (!$canAddLeft && !$canAddRight) {
                break;
            }

            // We could add a tab on either the left of the right, so we
            // decide based on distance from the focused tab. This
            // keeps us relatively balanced where possible.
            if ($canAddLeft && $canAddRight) {
                $canAddLeft = ($focused - $left) <= ($right - $focused);
                $canAddRight = !$canAddLeft;
            }

            if ($canAddLeft) {
                $selectedTabs->prepend($tabs[$left]);
                $left--;
            }

            if ($canAddRight) {
                $selectedTabs->push($tabs[$right]);
                $right++;
            }
        }

        return [++$left, --$right];
    }

    /**
     * @param  array<int|string, Hotkey>  $hotkeys
     */
    protected function renderHotkeySubset(array $hotkeys): void
    {
        foreach ($hotkeys as $hotkey) {
            $hotkey->init($this->currentCommand, $this->dashboard);

            if ($hotkey->visible()) {
                $this->hotkey($hotkey->keyDisplay(), $hotkey->makeLabel() ?? '');
            }
        }

        $this->line(
            $this->centerHorizontally($this->hotkeys(), $this->width)->first()
        );
    }

    /**
     * Build a map of the box glyphs used throughout rendering.
     *
     * @return array<string, string>
     */
    protected function buildBoxMap(string $box): array
    {
        $lines = explode("\n", $box);
        $lines = array_map(fn($line) => mb_str_split(trim($line)), $lines);

        return [
            '╭' => $lines[0][0] ?? '╭',
            '─' => $lines[0][1] ?? '─',
            '┬' => $lines[0][2] ?? '┬',
            '╮' => $lines[0][4] ?? '╮',
            '├' => $lines[1][0] ?? '├',
            '┼' => $lines[1][2] ?? '┼',
            '┤' => $lines[1][4] ?? '┤',
            '│' => $lines[2][0] ?? '│',
            '╰' => $lines[3][0] ?? '╰',
            '┴' => $lines[3][2] ?? '┴',
            '╯' => $lines[3][4] ?? '╯',
        ];
    }

    protected function replaceLastOccurrence(string $subject, string $search, string $replace): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, 0, $position) . $replace . substr($subject, $position + strlen($search));
    }

    protected function mbStrPad(string $string, int $length, string $pad = ' ', int $padType = STR_PAD_RIGHT): string
    {
        if (function_exists('mb_str_pad')) {
            return mb_str_pad($string, $length, $pad, $padType);
        }

        $stringLength = mb_strlen($string);

        if ($stringLength >= $length || $pad === '') {
            return $string;
        }

        $padLength = $length - $stringLength;

        [$left, $right] = match ($padType) {
            STR_PAD_LEFT => [$padLength, 0],
            STR_PAD_BOTH => [intdiv($padLength, 2), $padLength - intdiv($padLength, 2)],
            default => [0, $padLength],
        };

        return $this->mbPadRepeat($pad, $left) . $string . $this->mbPadRepeat($pad, $right);
    }

    protected function mbPadRepeat(string $pad, int $length): string
    {
        if ($length <= 0 || $pad === '') {
            return '';
        }

        $padLength = mb_strlen($pad);

        $repeated = str_repeat($pad, (int) ceil($length / $padLength));

        return mb_substr($repeated, 0, $length);
    }
}

// Saving for later! Not sure why!
// https://antofthy.gitlab.io/info/ascii/Spinners.txt
// $spinners = [
//    "⡀⠄⠂⠁⠈⠐⠠⢀",
//    "⣀⡄⠆⠃⠉⠘⠰⢠",
//    "⢿⣻⣽⣾⣷⣯⣟⡿",
//    "⣶⣧⣏⡟⠿⢻⣹⣼",
//    "⡈⠔⠢⢁",
//    "⣀⢄⢂⢁⡈⡐⡠",
//    "⡀⠄⠂⠁⠁⠂⠄",
//    "⢀⠠⠐⠈⠈⠐⠠",
//    "⡀⠄⠂⠁⠈⠐⠠⢀⠠⠐⠈⠁⠂⠄",
//    "⣤⠶⠛⠛⠶",
//    "⣀⡠⠤⠢⠒⠊⠉⠑⠒⠔⠤⢄",
//    "⢸⣸⢼⢺⢹⡏⡗⡧⣇⡇",
//    "⢸⣸⢼⢺⢹⢺⢼⣸⢸⡇⣇⡧⡗⡏⡗⡧⣇⡇",
//    "⠁⠂⠄⡀⡈⡐⡠⣀⣁⣂⣄⣌⣔⣤⣥⣦⣮⣶⣷⣿⡿⠿⢟⠟⡛⠛⠫⢋⠋⠍⡉⠉⠑⠡⢁",
//    "__\|/__"
// ];
// $prompt->frames->frame(mb_str_split($spinners[2]))
