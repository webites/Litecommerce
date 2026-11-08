<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Infrastructure;

use InvalidArgumentException;
use Litecommerce\Modules\Notifications\Contracts\PanelMessage;
use Litecommerce\Modules\Notifications\Contracts\PanelMessageRegistry;
use Throwable;

final class InMemoryPanelMessageRegistry implements PanelMessageRegistry
{
    /** @var array<string, callable(): iterable<mixed>> Plugin code may return anything, so results are checked. */
    private array $providers = [];

    public function register(string $sourceId, callable $provider): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_.:-]{0,99}$/', $sourceId) !== 1) {
            throw new InvalidArgumentException("Invalid panel message source id '{$sourceId}'.");
        }

        if (isset($this->providers[$sourceId])) {
            throw new InvalidArgumentException("Panel message source '{$sourceId}' is already registered.");
        }

        $this->providers[$sourceId] = $provider;
    }

    public function collect(): array
    {
        $messages = [];

        foreach ($this->providers as $sourceId => $provider) {
            try {
                foreach ($provider() as $message) {
                    if ($message instanceof PanelMessage && str_starts_with($message->key, $sourceId.'.') && ! isset($messages[$message->key])) {
                        $messages[$message->key] = $message;
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return array_values($messages);
    }
}
