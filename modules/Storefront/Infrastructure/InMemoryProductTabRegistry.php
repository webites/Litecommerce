<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Storefront\Contracts\ProductTabContext;
use Litecommerce\Modules\Storefront\Contracts\ProductTabData;
use Litecommerce\Modules\Storefront\Contracts\ProductTabRegistry;

/** Bound as a singleton, repopulated every request as each Enabled plugin's boot() registers its tabs — same lifetime convention as InMemoryStorefrontSlotRegistry. */
final class InMemoryProductTabRegistry implements ProductTabRegistry
{
    /** @var list<array{id: string, extension_id: string, label: string, render: \Closure(ProductTabContext): string}> */
    private array $tabs = [];

    public function register(string $id, string $extensionId, string $label, \Closure $render): void
    {
        $this->tabs[] = ['id' => $id, 'extension_id' => $extensionId, 'label' => $label, 'render' => $render];
    }

    public function all(ProductTabContext $context): array
    {
        $result = [];

        foreach ($this->tabs as $tab) {
            try {
                $result[] = new ProductTabData($tab['id'], $tab['label'], (string) ($tab['render'])($context));
            } catch (\Throwable $exception) {
                Log::warning("Product tab renderer failed for [{$tab['extension_id']}] on tab [{$tab['id']}].", ['exception' => $exception]);
            }
        }

        return $result;
    }
}
