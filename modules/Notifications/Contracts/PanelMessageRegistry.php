<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Contracts;

/**
 * Where modules and plugins publish messages for the panel dashboard and the banner. A source registers one provider that
 * derives its current messages from its own state, so a message disappears when its cause is gone. Providers run on every
 * panel page: keep them cheap (no network, no heavy queries) and return already translated text.
 */
interface PanelMessageRegistry
{
    /**
     * @param  string  $sourceId  e.g. "core.updates" or "acme-shipping"; every message key of the provider must start with "<sourceId>."
     * @param  callable(): iterable<PanelMessage>  $provider
     */
    public function register(string $sourceId, callable $provider): void;

    /** @return list<PanelMessage> */
    public function collect(): array;
}
