<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Infrastructure;

use Litecommerce\Modules\Themes\Contracts\ThemeCatalog;
use Litecommerce\Modules\Themes\Contracts\ThemeData;
use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;

final class EloquentThemeCatalog implements ThemeCatalog
{
    public function all(): array
    {
        return Theme::query()->orderBy('name')->get()->map(self::toData(...))->all();
    }

    public function find(string $id): ?ThemeData
    {
        $theme = Theme::query()->find($id);

        return $theme === null ? null : self::toData($theme);
    }

    public function active(): ?ThemeData
    {
        $theme = Theme::query()->where('status', ThemeStatus::Active->value)->first();

        return $theme === null ? null : self::toData($theme);
    }

    private static function toData(Theme $theme): ThemeData
    {
        $requiredPlugins = $theme->manifest['dependencies'] ?? [];

        return new ThemeData(
            id: $theme->id,
            name: $theme->name,
            version: $theme->version,
            engine: $theme->engine,
            status: ThemeStatus::from($theme->status),
            requiredPluginIds: array_values(array_map(static fn (array $dependency): string => $dependency['id'], $requiredPlugins)),
            installedAt: $theme->installed_at === null ? null : \DateTimeImmutable::createFromInterface($theme->installed_at),
            activatedAt: $theme->activated_at === null ? null : \DateTimeImmutable::createFromInterface($theme->activated_at),
            packageSha256: $theme->package_sha256,
        );
    }
}
