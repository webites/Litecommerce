<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Infrastructure;

use Litecommerce\Modules\Themes\Contracts\ThemeSettingsStore;
use Litecommerce\Modules\Themes\Infrastructure\Models\ThemeSetting;

final class EloquentThemeSettingsStore implements ThemeSettingsStore
{
    public function get(string $themeId, string $key, ?string $default = null): ?string
    {
        $value = ThemeSetting::query()->where('theme_id', $themeId)->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public function all(string $themeId): array
    {
        return ThemeSetting::query()->where('theme_id', $themeId)->pluck('value', 'key')->all();
    }
}
