<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;
use Litecommerce\Modules\Themes\Contracts\ThemeCacheGroups;
use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;

/**
 * Assets are published under the existing 'public' disk (storage/app/public,
 * served through the public/storage symlink the installer already creates
 * for Media — Media\Infrastructure\FilesystemPublicStorageLinker, P1-003)
 * rather than a second, theme-specific symlink.
 */
final class EloquentActiveThemeResolver implements ActiveThemeResolver
{
    public function __construct(
        private readonly string $publicDisk,
        private readonly string $publicPath,
        private readonly ObjectCache $cache,
    ) {}

    public function id(): ?string
    {
        $metadata = $this->metadata();

        return is_string($metadata['id']) ? $metadata['id'] : null;
    }

    public function assetUrl(string $relativePath): ?string
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        $path = "{$this->publicPath}/{$id}/".ltrim($relativePath, '/');
        $disk = Storage::disk($this->publicDisk);
        $url = $disk->url($path);

        try {
            return $url.'?v='.$disk->lastModified($path);
        } catch (\Throwable) {
            return $url;
        }
    }

    public function supportsDarkMode(): bool
    {
        return $this->metadata()['supports_dark_mode'] === true;
    }

    public function menuLocations(): array
    {
        $raw = $this->metadata()['menu_locations'];

        $locations = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null) || $entry['id'] === '') {
                continue;
            }
            $locations[] = ['id' => $entry['id'], 'label' => is_string($entry['label'] ?? null) ? $entry['label'] : $entry['id']];
        }

        return $locations;
    }

    /** @return array{id: string|null, supports_dark_mode: bool, menu_locations: array<int, mixed>} */
    private function metadata(): array
    {
        return $this->cache->remember(ThemeCacheGroups::ACTIVE, 'metadata', function (): array {
            $theme = Theme::query()
                ->where('status', ThemeStatus::Active->value)
                ->first(['id', 'manifest']);
            $manifest = is_array($theme?->manifest) ? $theme->manifest : [];
            $locations = $manifest['menu_locations'] ?? [];

            return [
                'id' => is_string($theme?->id) ? $theme->id : null,
                'supports_dark_mode' => ($manifest['supports_dark_mode'] ?? false) === true,
                'menu_locations' => is_array($locations) ? $locations : [],
            ];
        });
    }
}
