<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface SettingsTabRegistry
{
    /**
     * @param  callable(SettingsTabContext): SettingsTabContent  $renderer  Closures and [$object, 'method'] are supported.
     * @param  \Closure(): bool  $visible
     */
    public function register(string $id, string $label, int $position, callable $renderer, \Closure $visible): void;

    /** @return list<array{id: string, label: string}> */
    public function visible(): array;

    public function render(string $id, SettingsTabContext $context): ?SettingsTabContent;
}
