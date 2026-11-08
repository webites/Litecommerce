<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure\Repositories;

use Illuminate\Support\Facades\Schema;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Contracts\StoreCacheGroups;
use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsData;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Throwable;

final readonly class EloquentStoreSettingsReader implements StoreSettingsReader
{
    public function __construct(
        private MediaLibrary $media,
        private StoreSettingsStore $settings,
        private ObjectCache $cache,
    ) {}

    public function current(): StoreSettingsData
    {
        $values = $this->cache->remember(
            StoreCacheGroups::SETTINGS,
            'current',
            fn (): array => $this->readCurrent(),
        );

        return new StoreSettingsData(
            storeName: (string) $values['store_name'],
            logoMediaId: is_int($values['logo_media_id']) ? $values['logo_media_id'] : null,
            logoUrl: is_string($values['logo_url']) ? $values['logo_url'] : null,
            logoSrcset: is_string($values['logo_srcset']) ? $values['logo_srcset'] : null,
            logoWidth: is_int($values['logo_width']) ? $values['logo_width'] : null,
            logoHeight: is_int($values['logo_height']) ? $values['logo_height'] : null,
            blogEnabled: (bool) $values['blog_enabled'],
            catalogProductsPerPage: (int) $values['catalog_products_per_page'],
        );
    }

    /** @return array<string, bool|int|string|null> */
    private function readCurrent(): array
    {
        try {
            $hasSettings = Schema::hasTable('store_settings');
        } catch (Throwable) {
            $hasSettings = false;
        }

        if (! $hasSettings) {
            return $this->defaults();
        }

        $values = $this->settings->all();
        $storeName = $values[StoreSettingKeys::StoreName] ?? 'Litecommerce';
        $logo = $values[StoreSettingKeys::StoreLogoMediaId] ?? null;
        $blogEnabled = $values[StoreSettingKeys::BlogEnabled] ?? true;
        $catalogProductsPerPage = $values[StoreSettingKeys::CatalogProductsPerPage] ?? 24;
        $logoId = is_int($logo) ? $logo : null;
        $logoMedia = $logoId === null ? null : $this->media->find($logoId);

        return [
            'store_name' => is_string($storeName) ? $storeName : 'Litecommerce',
            'logo_media_id' => $logoId,
            'logo_url' => $logoMedia?->url,
            'logo_srcset' => $logoMedia?->srcset(),
            'logo_width' => $logoMedia?->width,
            'logo_height' => $logoMedia?->height,
            'blog_enabled' => is_bool($blogEnabled) ? $blogEnabled : true,
            'catalog_products_per_page' => is_int($catalogProductsPerPage)
                ? max(1, min(100, $catalogProductsPerPage))
                : 24,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    private function defaults(): array
    {
        return [
            'store_name' => 'Litecommerce',
            'logo_media_id' => null,
            'logo_url' => null,
            'logo_srcset' => null,
            'logo_width' => null,
            'logo_height' => null,
            'blog_enabled' => true,
            'catalog_products_per_page' => 24,
        ];
    }
}
