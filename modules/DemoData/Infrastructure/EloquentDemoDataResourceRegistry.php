<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Infrastructure;

use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\DemoData\Infrastructure\Models\DemoDataResource;

final class EloquentDemoDataResourceRegistry implements DemoDataResourceRegistry
{
    public function record(string $batchId, string $contributor, string $resourceType, int|string $resourceId): void
    {
        $position = (int) DemoDataResource::query()->where('batch_id', $batchId)->max('position') + 1;

        DemoDataResource::query()->create([
            'batch_id' => $batchId,
            'contributor' => $contributor,
            'resource_type' => $resourceType,
            'resource_id' => (string) $resourceId,
            'position' => $position,
        ]);
    }

    public function ids(string $batchId, string $contributor, string $resourceType): array
    {
        return DemoDataResource::query()
            ->where('batch_id', $batchId)
            ->where('contributor', $contributor)
            ->where('resource_type', $resourceType)
            ->orderBy('position')
            ->pluck('resource_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }
}
