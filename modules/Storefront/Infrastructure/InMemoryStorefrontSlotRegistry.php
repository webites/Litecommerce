<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Storefront\Contracts\StorefrontSlotRegistry;

/** Bound as a singleton, repopulated every request as each Enabled plugin's boot() registers its renderers — same lifetime convention as Extensions\Infrastructure\InMemoryPanelSlotRegistry. */
final class InMemoryStorefrontSlotRegistry implements StorefrontSlotRegistry
{
    /** @var array<string, list<array{extension_id: string, renderer: \Closure(): string}>> */
    private array $renderers = [];

    public function register(string $slot, string $extensionId, \Closure $render): void
    {
        $this->renderers[$slot][] = ['extension_id' => $extensionId, 'renderer' => $render];
    }

    public function render(string $slot): string
    {
        $html = '';

        foreach ($this->renderers[$slot] ?? [] as $entry) {
            try {
                $html .= (string) ($entry['renderer'])();
            } catch (\Throwable $exception) {
                Log::warning("Storefront slot renderer failed for [{$entry['extension_id']}] on slot [{$slot}].", ['exception' => $exception]);
            }
        }

        return $html;
    }
}
