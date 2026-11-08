<?php

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class CommittedEvent
{
    /** Delivers a committed fact to every listener; one failing listener is logged and never blocks the rest or the request. */
    public static function dispatch(object $event): void
    {
        $events = Event::getFacadeRoot();

        if (! $events instanceof Dispatcher) {
            $events->dispatch($event);

            return;
        }

        foreach ($events->getListeners($event::class) as $listener) {
            try {
                $listener($event::class, [$event]);
            } catch (\Throwable $exception) {
                Log::error('A listener of a committed event failed.', ['event' => $event::class, 'exception' => $exception]);
            }
        }
    }
}
