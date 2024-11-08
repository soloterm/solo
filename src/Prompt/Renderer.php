<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com>
 * @link https://aaronfrancis.com
 * @link https://twitter.com/aarondfrancis
 */

namespace AaronFrancis\Solo\Prompt;

use AaronFrancis\Solo\Commands\Command;
use AaronFrancis\Solo\Contracts\Theme;
use AaronFrancis\Solo\Facades\Solo;
use Chewie\Concerns\Aligns;
use Chewie\Concerns\DrawsHotkeys;
use Chewie\Output\Util;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Prompts\Themes\Default\Concerns\DrawsScrollbars;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use Laravel\Prompts\Themes\Default\Renderer as PromptsRenderer;

use function Laravel\Prompts\info;

class Renderer extends PromptsRenderer
{
    use Aligns, DrawsHotkeys, DrawsScrollbars, InteractsWithStrings;

    public Theme $theme;

    public Dashboard $dashboard;

    public Command $currentCommand;

    public int $width;

    public int $height;

    public function setup(Dashboard $dashboard): void
    {
        $this->dashboard = $dashboard;
        $this->theme = Solo::makeTheme();
        $this->currentCommand = $dashboard->currentCommand();
        $this->width = $dashboard->width;
        $this->height = $dashboard->height;
    }

    public function __invoke(Dashboard $dashboard): string
    {
        $this->setup($dashboard);

        $this->renderTabs();
        $this->renderProcessState();
        $this->renderContentPane();
        $this->renderHotkeys();

        return $this;
    }

    public function __toString()
    {
        // We're running the output right up to the edge,
        // so we can't afford phantom newlines.
        return Str::chopEnd($this->output, "\n");
    }

    protected function renderTabs(): void
    {
        $tabs = collect($this->dashboard->commands)->map(fn(Command $command) => [
            'command' => $command,
            'display' => " $command->name ",
            'focused' => $command->isFocused()
        ]);

        // Find the index of the focused tab.
        $focused = $tabs->pluck('focused')->search(value: true, strict: true);

        // Pass it through to figure out what all the visible
        // tabs should be, based on our available width.
        [$start, $end] = $this->calculateVisibleTabs($tabs, $focused, $this->width);

        // Slice out the visible tabs based on the indexes we
        // received back, then add the styling.
        $selectedTabs = $tabs
            ->slice($start, $end - $start + 1)
            ->map(fn($tab) => $this->styleTab($tab['command'], $tab['display']))
            ->implode(' ');

        // Some tabs on the left side weren't able to be
        // rendered, so we need to show an indicator.
        if ($start > 0) {
            // Allow for 2 parens, an arrow, a space, and 1-2 digits. If there is only
            // one digit we pad the full string to 6 characters to prevent jumpiness
            // and so we can reliably use 6 in the calculateVisibleTabs method.
            $more = $this->theme->tabMore(
                mb_str_pad("(← $start)", 6, ' ', STR_PAD_RIGHT)
            );

            $selectedTabs = "{$more}{$selectedTabs}";
        }

        // We're missing some tabs on the right side, so show an indicator.
        if ($end < $tabs->count() - 1) {
            // Same deal as above about padding to 6.
            $more = mb_str_pad('(' . ($tabs->count() - 1 - $end) . ' →)', 6, ' ', STR_PAD_LEFT);

            // How much space do we have left?
            $remaining = $this->width - mb_strlen(Util::stripEscapeSequences($selectedTabs)) - mb_strlen($more);

            // If we have enough to do something interesting, then we
            // grab the next tab and truncate it. (5 is arbitrary.)
            if ($remaining > 5) {
                $peek = $tabs->get($end + 1);
                $truncated = $this->truncate($peek['display'], $remaining - 1) . ' ';

                $peek = $this->styleTab($peek['command'], $truncated);
            } else {
                // Otherwise just show some spaces
                $peek = str_repeat(' ', max($remaining, 0));
            }

            $selectedTabs = $selectedTabs . $peek . $this->theme->tabMore($more);
        }

        $this->line($selectedTabs);
    }

    protected function styleTab(Command $command, string $name): string
    {
        if ($command->processStopped()) {
            $name = $this->theme->tabStopped($name);
        }

        return $command->isFocused() ? $this->theme->tabFocused($name) : $this->theme->tabBlurred($name);
    }

    protected function renderProcessState(): void
    {
        $state = $this->currentCommand->processRunning()
            ? $this->theme->processRunning(' Running: ')
            : $this->theme->processStopped(' Stopped: ');

        $state .= $this->theme->dim($this->currentCommand->command);

        $this->line($state);
    }

    protected function renderContentPane(): void
    {
        $allowedLines = $this->currentCommand->scrollPaneHeight();
        $wrappedLines = $this->currentCommand->wrappedLines();
        $start = $this->currentCommand->scrollIndex;
        $visible = $wrappedLines->slice($start, $allowedLines);

        // Add one since we're showing what lines they're viewing.
        // There's no such thing as a zeroth line.
        $this->renderBoxTop($start + 1, $start + $visible->count(), $wrappedLines->count());

        // Try to scroll the content, which may or may not have an
        // effect, depending on how much content there is.
        $scrolled = $this->scrollbar(
            // Subtract 1 for the left box border and 1 for the space after it.
            $visible, $start, $allowedLines, $wrappedLines->count(), $this->width - 2
        );

        // If this conditional is true then it means that there wasn't
        // enough content to scroll, so we have to pretend by padding.
        if ($scrolled === $visible) {
            $scrolled = $this->padScrolledContent($scrolled, $allowedLines);
        }

        $scrolled->each(function ($line) {
            str($line)
                // Remove the gray scrollbar and replace it with
                // our own that matches the theme's box.
                ->replaceLast($this->gray('│'), $this->theme->boxBorder($this->box('│')))

                // Replace the handle with the user's preferred handle.
                ->replaceLast($this->cyan('┃'), $this->theme->boxHandle())

                // Only need to add the left side, because the
                // right side is made up of the scrollbar.
                ->prepend($this->theme->boxBorder($this->box('│') . ' '))

                // Output
                ->pipe($this->line(...));
        });

        // Box bottom border
        $this->line(
            $this->theme->boxBorder(
                $this->box('╰') . str_repeat($this->box('─'), $this->width - 2) . $this->box('╯')
            )
        );
    }

    protected function renderBoxTop(int $start, int $stop, int $total): void
    {
        // Otherwise it'd read "Viewing [1-0] of 0", which is insane.
        if ($total === 0) {
            $start = 0;
        }

        $count = "Viewing [$start-$stop] of $total";
        $state = $this->currentCommand->paused ? '(Paused)' : '(Live)';

        $stateTreatment = $this->currentCommand->paused ? 'logsPaused' : 'logsLive';

        $border = ''
            . $this->theme->boxBorder($this->box('╭'))
            // 3 spaces + 3 border pieces = 6
            . $this->theme->boxBorder(str_repeat($this->box('─'), $this->width - strlen($state) - strlen($count) - 6))
            . ' '
            . $this->theme->dim($count)
            . ' '
            . $this->theme->{$stateTreatment}($state)
            . ' '
            . $this->theme->boxBorder($this->box('─'))
            . $this->theme->boxBorder($this->box('╮'));

        $this->line($border);
    }

    protected function renderHotkeys(): void
    {
        $this->pinToBottom($this->height, function () {
            $this->hotkey('←/h', 'Previous');
            $this->hotkey('→/l', 'Next');

            $this->currentCommand->paused ? $this->hotkey('f', 'Follow') : $this->hotkey('p', 'Pause ');

            $this->hotkey('c', 'Clear');

            $this->hotkey('s', $this->currentCommand->processRunning() ? 'Stop ' : 'Start');

            $this->hotkey('r', 'Restart');

            $this->hotkey('q', 'Quit');

            $this->line(
                $this->centerHorizontally($this->hotkeys(), $this->width)->first()
            );
        });
    }

    protected function box($part)
    {
        $box = $this->theme->box();
        // Example box
        // ╭─┬─╮
        // ├─┼─┤
        // │ │ │
        // ╰─┴─╯

        $lines = explode("\n", $box);
        $lines = array_map(fn($line) => mb_str_split($line), $lines);

        return match ($part) {
            '╭' => $lines[0][0],
            '─' => $lines[0][1],
            '┬' => $lines[0][2],
            '╮' => $lines[0][4],

            '├' => $lines[1][0],
            '┼' => $lines[1][2],
            '┤' => $lines[1][4],

            '│' => $lines[2][0],

            '╰' => $lines[3][0],
            '┴' => $lines[3][2],
            '╯' => $lines[3][4],
        };
    }

    protected function padScrolledContent(Collection $scrolled, int $allowedLines): Collection
    {
        // Fill out the collection with enough lines to fill the screen.
        while ($scrolled->count() < $allowedLines) {
            $scrolled->push('');
        }

        // Pad every line all the way to the right and add a pretend scrollbar.
        return $scrolled->map(function ($line) {
            // We use gray('│') because that's what the scrollbar
            // method does. We'll customize it further down.
            // (3 = 1 left bar + 1 space + 1 right bar.)
            return $this->pad($line, $this->width - 3) . $this->gray('│');
        });
    }

    protected function calculateVisibleTabs(
        Collection $tabs,
        int $focused,
        int $maxWidth,
        $moreOnLeft = false,
        $moreOnRight = false
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
}

// Saving for later! Not sure why!
// https://antofthy.gitlab.io/info/ascii/Spinners.txt
//$spinners = [
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
//];
// $prompt->frames->frame(mb_str_split($spinners[2]))
