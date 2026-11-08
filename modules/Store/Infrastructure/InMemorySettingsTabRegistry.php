<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;
use Throwable;

final class InMemorySettingsTabRegistry implements SettingsTabRegistry
{
    /** @var array<string, array{label: string, position: int, visible: \Closure(): bool, renderer: callable(SettingsTabContext): SettingsTabContent}> */
    private array $tabs = [];

    public function register(string $id, string $label, int $position, callable $renderer, \Closure $visible): void
    {
        $this->tabs[$id] = [
            'label' => $label,
            'position' => $position,
            'visible' => $visible,
            'renderer' => $renderer,
        ];
    }

    public function visible(): array
    {
        $visible = [];

        $tabs = $this->tabs;
        uasort($tabs, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        foreach ($tabs as $id => $tab) {
            if (($tab['visible'])()) {
                $visible[] = ['id' => $id, 'label' => $this->label($tab['label'])];
            }
        }

        return $visible;
    }

    public function render(string $id, SettingsTabContext $context): ?SettingsTabContent
    {
        $tab = $this->tabs[$id] ?? null;
        if ($tab === null || ! ($tab['visible'])()) {
            return null;
        }

        try {
            return ($tab['renderer'])($context);
        } catch (Throwable $exception) {
            Log::warning("Settings tab renderer failed for [{$id}].", ['exception' => $exception]);

            return SettingsTabContent::html('');
        }
    }

    private function label(string $label): string
    {
        return str_contains($label, '::') ? trans($label) : $label;
    }
}
