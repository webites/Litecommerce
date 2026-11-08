<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Storefront\Contracts\MenuWidgetRegistry;
use Throwable;

/** Bound as a singleton, repopulated every request as core and each enabled plugin's boot() register their widgets — same lifetime convention as InMemoryProductTabRegistry. */
final class InMemoryMenuWidgetRegistry implements MenuWidgetRegistry
{
    /** @var list<array{key: string, extension_id: string, label: string, render: \Closure(?string): string}> */
    private array $widgets = [];

    public function register(string $key, string $extensionId, string $label, \Closure $render): void
    {
        $this->widgets[] = ['key' => $key, 'extension_id' => $extensionId, 'label' => $label, 'render' => $render];
    }

    public function all(): array
    {
        return array_map(
            static fn (array $widget): array => ['key' => $widget['key'], 'extensionId' => $widget['extension_id'], 'label' => $widget['label']],
            $this->widgets,
        );
    }

    public function render(string $key, ?string $labelOverride): ?string
    {
        foreach ($this->widgets as $widget) {
            if ($widget['key'] !== $key) {
                continue;
            }

            try {
                return ($widget['render'])($labelOverride);
            } catch (Throwable $exception) {
                Log::warning("Menu widget renderer failed for [{$widget['extension_id']}] on widget [{$key}].", ['exception' => $exception]);

                return null;
            }
        }

        return null;
    }
}
