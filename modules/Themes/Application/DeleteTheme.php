<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Application;

use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Themes\Contracts\ThemeStateException;
use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;
use Litecommerce\Modules\Themes\Infrastructure\ThemeAssetPublisher;

final class DeleteTheme
{
    public function __construct(
        private readonly ThemeAssetPublisher $assets,
        private readonly string $disk,
        private readonly string $basePath,
    ) {}

    public function execute(string $themeId): void
    {
        $theme = Theme::query()->findOrFail($themeId);

        if ($theme->status === ThemeStatus::Active->value) {
            throw new ThemeStateException('still_active', 'Deactivate the theme before deleting it.');
        }

        $this->assets->unpublish($themeId);
        Storage::disk($this->disk)->deleteDirectory($this->basePath.'/'.$themeId);
        // Cascades to theme_settings via the FK.
        $theme->delete();
    }
}
