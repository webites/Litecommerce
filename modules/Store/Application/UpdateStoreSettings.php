<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

final readonly class UpdateStoreSettings
{
    public function __construct(
        private MediaLibrary $media,
        private StoreSettingsStore $settings,
    ) {}

    public function basics(string $storeName, bool $blogEnabled, int $catalogProductsPerPage, string $timezone): void
    {
        $this->settings->setMany([
            StoreSettingKeys::StoreName => $storeName,
            StoreSettingKeys::BlogEnabled => $blogEnabled,
            StoreSettingKeys::CatalogProductsPerPage => $catalogProductsPerPage,
            StoreSettingKeys::Timezone => $timezone,
        ]);
    }

    public function replaceLogo(string $contents, string $filename, string $mimeType, string $staffId): void
    {
        DB::transaction(function () use ($contents, $filename, $mimeType, $staffId): void {
            $oldLogo = $this->settings->get(StoreSettingKeys::StoreLogoMediaId);
            $oldLogoId = is_int($oldLogo) ? $oldLogo : null;
            $storeName = $this->settings->get(StoreSettingKeys::StoreName, 'Litecommerce');
            $logo = $this->media->store($contents, $filename, $mimeType, (is_string($storeName) ? $storeName : 'Litecommerce').' logo', $staffId);
            $this->settings->set(StoreSettingKeys::StoreLogoMediaId, $logo->id);

            if ($oldLogoId !== null) {
                $this->media->delete($oldLogoId);
            }
        });
    }

    public function removeLogo(): void
    {
        DB::transaction(function (): void {
            $logo = $this->settings->get(StoreSettingKeys::StoreLogoMediaId);
            $logoId = is_int($logo) ? $logo : null;
            $this->settings->remove(StoreSettingKeys::StoreLogoMediaId);

            if ($logoId !== null) {
                $this->media->delete($logoId);
            }
        });
    }
}
