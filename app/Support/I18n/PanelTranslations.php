<?php

declare(strict_types=1);

namespace App\Support\I18n;

// Flattens every module's own "panel" translation group into one Inertia prop, keyed by module
// namespace — the panel's React side has no separate translation catalog of its own.
final class PanelTranslations
{
    /** @return array<string, array<string, mixed>> */
    public function forCurrentLocale(): array
    {
        // 'app' carries generic panel chrome (layout, nav grouping) that isn't any one module's concern.
        $translations = ['app' => trans('panel')];

        foreach (array_keys(config('modules.modules', [])) as $namespace) {
            $group = trans("{$namespace}::panel");
            $translations[$namespace] = is_array($group) ? $group : [];
        }

        return $translations;
    }
}
