<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

/** Generic key-value settings for the fields a theme's manifest declares — same shape as Extensions\Contracts\ExtensionSettingsStore, kept as a separate store since themes and plugins are unrelated entities. */
interface ThemeSettingsStore
{
    public function get(string $themeId, string $key, ?string $default = null): ?string;

    /** @return array<string, string> */
    public function all(string $themeId): array;
}
