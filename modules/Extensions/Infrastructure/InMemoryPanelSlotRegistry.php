<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Extensions\Contracts\PanelSlotRegistry;

/**
 * Bound as a singleton (one instance per request, like
 * Payments\Infrastructure\InMemoryPaymentMethodRegistry) — repopulated
 * every request as each Enabled plugin's boot() registers its renderers.
 */
final class InMemoryPanelSlotRegistry implements PanelSlotRegistry
{
    /** @var array<string, list<array{extension_id: string, renderer: \Closure(array<string, string>): string}>> */
    private array $renderers = [];

    public function register(string $slot, string $extensionId, \Closure $renderer): void
    {
        $this->renderers[$slot][] = ['extension_id' => $extensionId, 'renderer' => $renderer];
    }

    public function render(string $slot, array $context = []): array
    {
        $rendered = [];

        foreach ($this->renderers[$slot] ?? [] as $entry) {
            try {
                $rendered[] = ['extension_id' => $entry['extension_id'], 'html' => (string) ($entry['renderer'])($context)];
            } catch (\Throwable $exception) {
                // Optional-hook slot — a broken plugin fragment never breaks
                // the page ("Błąd opcjonalnego elementu widoku: log i
                // pominięcie", 08-extensions.md).
                Log::warning("Extension panel slot renderer failed for [{$entry['extension_id']}] on slot [{$slot}].", ['exception' => $exception]);
            }
        }

        return $rendered;
    }
}
