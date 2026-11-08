<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Repositories;

use Litecommerce\Modules\Catalog\Contracts\BrandCatalog;
use Litecommerce\Modules\Catalog\Contracts\BrandSummaryData;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Brand;
use Litecommerce\Modules\Catalog\Infrastructure\Models\BrandTranslation;
use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;

final class EloquentBrandCatalog implements BrandCatalog
{
    public function findSlugInLocale(int $id, string $locale): ?string
    {
        return BrandTranslation::query()->where('brand_id', $id)->where('locale', $locale)->value('slug');
    }

    public function listAll(string $locale): array
    {
        return Brand::query()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->orderBy('id')
            ->get()
            ->map(static function (Brand $brand): BrandSummaryData {
                $translation = $brand->translations->first();

                return new BrandSummaryData(
                    id: (int) $brand->getKey(),
                    name: $translation === null ? 'Marka #'.$brand->getKey() : $translation->name,
                    slug: $translation === null ? '' : $translation->slug,
                );
            })->values()->all();
    }

    public function find(int $id, string $locale): ?BrandSummaryData
    {
        $brand = Brand::query()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->find($id);
        $translation = $brand?->translations->first();

        if ($brand === null || $translation === null) {
            return null;
        }

        return new BrandSummaryData(id: (int) $brand->getKey(), name: $translation->name, slug: $translation->slug);
    }

    public function matchArchive(string $slug, string $locale): ?ArchetypeMatch
    {
        $brand = Brand::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where('slug', $slug))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->first();
        $translation = $brand?->translations->first();

        if ($brand === null || $translation === null) {
            return null;
        }

        return new ArchetypeMatch(
            title: $translation->name,
            description: $translation->description_html,
            blockContext: ['brand_id' => (int) $brand->getKey()],
            tags: [
                'brand_name' => $translation->name,
                'brand_description' => trim(strip_tags($translation->description_html ?? '')),
            ],
        );
    }
}
