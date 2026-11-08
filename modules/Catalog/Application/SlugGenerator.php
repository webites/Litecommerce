<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generated once, from the row's name in that locale, at the moment a
 * translation row is first created — never regenerated on a later name
 * edit, so a public URL does not silently change underneath it (no
 * slug-editing UI exists yet, see "Zakres i świadome cięcia",
 * docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
 * Shared by products, categories and brands — each has its own
 * (locale, slug) unique index, so uniqueness is checked per translation
 * model, not globally.
 */
final class SlugGenerator
{
    /** @param  class-string<Model>  $translationModel  Must expose 'locale'/'slug' columns and support ::query(). */
    public function generate(string $name, string $locale, string $translationModel, string $fallbackBase): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = $fallbackBase;
        }

        $slug = $base;
        for ($suffix = 2; $translationModel::query()->where('locale', $locale)->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
