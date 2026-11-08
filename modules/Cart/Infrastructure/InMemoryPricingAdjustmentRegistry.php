<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure;

use Litecommerce\Modules\Cart\Contracts\PricingAdjustment;
use Litecommerce\Modules\Cart\Contracts\PricingAdjustmentRegistry;

final class InMemoryPricingAdjustmentRegistry implements PricingAdjustmentRegistry
{
    /** @var list<array{priority: int, adjustment: PricingAdjustment}> */
    private array $entries = [];

    public function register(PricingAdjustment $adjustment, int $priority = 100): void
    {
        $this->entries[] = ['priority' => $priority, 'adjustment' => $adjustment];
        usort($this->entries, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
    }

    public function all(): array
    {
        return array_map(static fn (array $entry): PricingAdjustment => $entry['adjustment'], $this->entries);
    }
}
