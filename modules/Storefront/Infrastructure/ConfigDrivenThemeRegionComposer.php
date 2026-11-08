<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Litecommerce\Modules\Storefront\Contracts\ThemeRegionComposer;

final class ConfigDrivenThemeRegionComposer implements ThemeRegionComposer
{
    public function render(string $group, array $data = []): string
    {
        // Not config("storefront.regions.{$group}") — $group ("catalog.show")
        // itself contains a dot, and Laravel's dot-notation config lookup
        // would split THAT too, walking regions->catalog->show instead of
        // reading the "catalog.show" key literally.
        $regions = config('storefront.regions', [])[$group] ?? [];
        $html = '';

        foreach ($regions as $region) {
            $view = "theme::partials.{$region}";

            if (! View::exists($view)) {
                continue;
            }

            try {
                $html .= View::make($view, $data)->render();
            } catch (\Throwable $exception) {
                Log::warning("Theme region [{$region}] in group [{$group}] failed to render.", ['exception' => $exception]);
            }
        }

        return $html;
    }
}
