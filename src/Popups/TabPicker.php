<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Popups;

use Cache;
use Generator;
use Laravel\Prompts\Key;
use SoloTerm\Solo\Events\Event;
use SoloTerm\Solo\Facades\Solo;
use SoloTerm\Solo\Support\CapturedQuickPickPrompt;
use Str;

class TabPicker extends Popup
{
    use HasForm;

    public bool $exitRequested = false;

    public static $recent = [];

    public function boot()
    {
        Solo::on(
            event: Event::ActivateTab,
            listener: function ($tab) {
                array_unshift(static::$recent, $tab);
                static::$recent = array_unique(static::$recent);
            },
            uniqueId: static::class
        );
    }

    public function form(): Generator
    {
        $names = collect(Solo::commands())
            ->map(function ($command, $name) {
                if (is_string($command)) {
                    return $name;
                }

                return $command->name ?? $name;
            });

        $names = $names->sort(
            $this->getSortByRecencyComparator($names->all())
        );

        yield $tab = new CapturedQuickPickPrompt(
            label: '',
            options: fn(string $value) => $names
                ->filter(fn($name) => empty($value) ? $names : Str::contains($name, $value, ignoreCase: true))
                ->values()
                ->all(),
            placeholder: 'Begin typing or press enter',
            hint: 'Press ESC to exit or Enter to choose.',
        );

        $tab = $tab->value();

        Solo::emit(Event::ActivateTab, $tab);

        $this->exitRequested = true;
    }

    public function handleInput($key)
    {
        if ($key === Key::ESCAPE) {
            $this->exitRequested = true;

            return;
        }

        $this->handleFormInput($key);
    }

    public function shouldClose(): bool
    {
        return $this->exitRequested;
    }

    /**
     * We extend the render function to make this popup "frameless".
     * Perhaps we should make that a built in option?
     */
    public function render(int $offsetX = 0, int $offsetY = 0): string
    {
        $output = $this->output();

        $rendered = "\e[H\e[{$offsetY}B\e[0m" . PHP_EOL;

        foreach (explode(PHP_EOL, $output) as $line) {
            $rendered .= "\e[{$offsetX}C " . $line . ' ' . PHP_EOL;
        }

        return $rendered;
    }

    protected function getSortByRecencyComparator(array $commands): callable
    {
        // Step 1: Retrieve the sort order from cache
        $sort = static::$recent;
        // Remove the first element, which is the tab we're on right now.
        $current = array_shift($sort);
        $current ??= $commands[0] ?? null;

        if (empty($sort)) {
            foreach ($commands as $command) {
                if ($command === $current) {
                    continue;
                }

                $sort[] = $command;
                break;
            }
        }

        // Step 2: Create a mapping of sort array elements to their positions
        $sortOrderMap = array_flip($sort);

        // Step 3: Define the default order value for elements not present in the sort array
        $defaultOrder = count($sort);

        // Step 4: Create a mapping of elements to their original index in $commands
        $originalOrderMap = [];
        foreach ($commands as $index => $element) {
            // Only store the first occurrence to handle unique elements
            if (!isset($originalOrderMap[$element])) {
                $originalOrderMap[$element] = $index;
            }
        }

        // Step 5: Return the comparison function for uasort
        return function ($a, $b) use ($sortOrderMap, $defaultOrder, $originalOrderMap) {
            // Determine sort order for $a
            if (isset($sortOrderMap[$a])) {
                $orderA = $sortOrderMap[$a];
            } else {
                // Assign default order based on original index to preserve original sequence
                $orderA = $defaultOrder + ($originalOrderMap[$a] ?? 0);
            }

            // Determine sort order for $b
            if (isset($sortOrderMap[$b])) {
                $orderB = $sortOrderMap[$b];
            } else {
                // Assign default order based on original index to preserve original sequence
                $orderB = $defaultOrder + ($originalOrderMap[$b] ?? 0);
            }

            // Compare the order values
            if ($orderA === $orderB) {
                // If the sort orders are the same, preserve original order based on original_index
                return ($originalOrderMap[$a] ?? 0) <=> ($originalOrderMap[$b] ?? 0);
            }

            return ($orderA < $orderB) ? -1 : 1;
        };
    }
}
