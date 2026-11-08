<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Litecommerce\Modules\Storefront\Contracts\MenuLocationRegistry;

/** Bound as a singleton, repopulated every request as each enabled plugin's boot() registers its locations — same lifetime convention as InMemoryMenuWidgetRegistry. */
final class InMemoryMenuLocationRegistry implements MenuLocationRegistry
{
    /** @var list<array{id: string, extension_id: string, label: string}> */
    private array $locations = [];

    public function register(string $id, string $extensionId, string $label): void
    {
        $this->locations[] = ['id' => $id, 'extension_id' => $extensionId, 'label' => $label];
    }

    public function all(): array
    {
        return array_map(
            static fn (array $location): array => ['id' => $location['id'], 'extensionId' => $location['extension_id'], 'label' => $location['label']],
            $this->locations,
        );
    }
}
