<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure;

use Litecommerce\Modules\Extensions\Contracts\ExtensionCatalog;
use Litecommerce\Modules\Extensions\Contracts\ExtensionData;
use Litecommerce\Modules\Extensions\Contracts\ExtensionStatus;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;

final class EloquentExtensionCatalog implements ExtensionCatalog
{
    public function all(): array
    {
        return Extension::query()->orderBy('name')->get()->map(self::toData(...))->all();
    }

    public function find(string $id): ?ExtensionData
    {
        $extension = Extension::query()->find($id);

        return $extension === null ? null : self::toData($extension);
    }

    public function isEnabled(string $id): bool
    {
        return Extension::query()->whereKey($id)->where('status', ExtensionStatus::Enabled->value)->exists();
    }

    private static function toData(Extension $extension): ExtensionData
    {
        $dependencies = $extension->manifest['dependencies'] ?? [];

        return new ExtensionData(
            id: $extension->id,
            name: $extension->name,
            version: $extension->version,
            type: $extension->type,
            status: ExtensionStatus::from($extension->status),
            dependencyIds: array_values(array_map(static fn (array $dependency): string => $dependency['id'], $dependencies)),
            installedAt: $extension->installed_at === null ? null : \DateTimeImmutable::createFromInterface($extension->installed_at),
            enabledAt: $extension->enabled_at === null ? null : \DateTimeImmutable::createFromInterface($extension->enabled_at),
            packageSha256: $extension->package_sha256,
        );
    }
}
