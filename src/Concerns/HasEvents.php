<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Concerns;

use SoloTerm\Solo\Events\Event;

trait HasEvents
{
    /**
     * @var array<string, callable[]>
     */
    private array $listeners = [];

    public function on(Event|string $event, callable $listener, ?string $uniqueId = null): void
    {
        $event = $event instanceof Event ? $event->value : $event;

        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        if ($uniqueId) {
            $this->listeners[$event][$uniqueId] = $listener;
        } else {
            $this->listeners[$event][] = $listener;
        }
    }

    public function off(Event|string $event, ?callable $listener = null, ?string $uniqueId = null): void
    {
        $event = $event instanceof Event ? $event->value : $event;

        if (!isset($this->listeners[$event])) {
            return;
        }

        if (!is_null($uniqueId)) {
            unset($this->listeners[$event][$uniqueId]);
        } elseif (!is_null($listener)) {
            $this->listeners[$event] = array_filter(
                $this->listeners[$event], fn($registered) => $registered !== $listener
            );
        }

        // If no listeners remain for the event, optionally unset it
        if (empty($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }
    }

    public function emit(Event|string $event, ...$data): void
    {
        $event = $event instanceof Event ? $event->value : $event;

        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            // Invoke the listener, passing the data.
            // The listener signature can accept any number of arguments
            $listener(...$data);
        }
    }
}
