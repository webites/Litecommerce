<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Repositories;

use Litecommerce\Modules\Catalog\Contracts\CategoryCatalog;
use Litecommerce\Modules\Catalog\Contracts\CategorySummaryData;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Category;
use Litecommerce\Modules\Catalog\Infrastructure\Models\CategoryTranslation;
use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;

final class EloquentCategoryCatalog implements CategoryCatalog
{
    public function matchArchive(string $slug, string $locale): ?ArchetypeMatch
    {
        $category = Category::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where('slug', $slug))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->first();
        $translation = $category?->translations->first();

        if ($category === null || $translation === null) {
            return null;
        }

        return new ArchetypeMatch(
            title: $translation->name,
            description: $translation->description_html,
            blockContext: ['category_id' => (int) $category->getKey()],
            // {{category_name}}/{{category_description}} — see Cms\Contracts\ArchetypeRegistry's
            // $availableTags docblock for where these are advertised to the editor.
            tags: [
                'category_name' => $translation->name,
                'category_description' => trim(strip_tags($translation->description_html ?? '')),
            ],
        );
    }

    public function findSlugInLocale(int $id, string $locale): ?string
    {
        return CategoryTranslation::query()->where('category_id', $id)->where('locale', $locale)->value('slug');
    }

    public function listAll(string $locale): array
    {
        return Category::query()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->orderBy('parent_id')->orderBy('position')->orderBy('id')
            ->get()
            ->map(static function (Category $category): CategorySummaryData {
                $translation = $category->translations->first();

                return new CategorySummaryData(
                    id: (int) $category->getKey(),
                    name: $translation === null ? 'Kategoria #'.$category->getKey() : $translation->name,
                    slug: $translation === null ? '' : $translation->slug,
                );
            })->values()->all();
    }

    public function find(int $id, string $locale): ?CategorySummaryData
    {
        $category = Category::query()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->find($id);
        $translation = $category?->translations->first();

        if ($category === null || $translation === null) {
            return null;
        }

        return new CategorySummaryData(id: (int) $category->getKey(), name: $translation->name, slug: $translation->slug);
    }
}
