<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Litecommerce\Modules\Search\Contracts\ProductSearchCriteria;
use Litecommerce\Modules\Search\Contracts\ProductSearchEngine;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemData;
use Litecommerce\Modules\Storefront\Contracts\ProductListingPageData;
use Litecommerce\Modules\Storefront\Contracts\ProductSearchListingBuilder;
use Litecommerce\Modules\Storefront\Contracts\ProductSearchListingData;

final readonly class DefaultProductSearchListingBuilder implements ProductSearchListingBuilder
{
    public function __construct(
        private ProductSearchEngine $search,
        private CurrencyRepository $currencies,
        private StoreSettingsReader $settings,
    ) {}

    public function build(string $locale, ?int $categoryId, array $query): ProductSearchListingData
    {
        $page = max(1, is_numeric($query['page'] ?? null) ? (int) $query['page'] : 1);
        $baseCurrency = $this->currencies->base();
        $currencyCode = $baseCurrency === null ? 'PLN' : $baseCurrency->code;
        $currencyDecimalPlaces = $baseCurrency === null ? 2 : $baseCurrency->decimalPlaces;
        $perPage = $this->settings->current()->catalogProductsPerPage;
        $criteria = new ProductSearchCriteria(
            query: mb_substr(trim(is_scalar($query['q'] ?? null) ? (string) $query['q'] : ''), 0, 100),
            locale: $locale,
            currencyCode: $currencyCode,
            categoryId: $categoryId,
            minimumPrice: $this->decimalFilter($query['min_price'] ?? null),
            maximumPrice: $this->decimalFilter($query['max_price'] ?? null),
            availableOnly: filter_var($query['available'] ?? false, FILTER_VALIDATE_BOOL),
            attributes: $this->attributeFilters($query['attributes'] ?? []),
            page: $page,
            perPage: $perPage,
        );
        $result = $this->search->search($criteria);
        $items = array_map(function ($hit) use ($locale, $currencyCode, $currencyDecimalPlaces): ProductListingItemData {
            $selected = $hit->variant;
            $baseUrl = route('storefront.catalog.show', ['locale' => $locale, 'slug' => $hit->product->slug]);

            return new ProductListingItemData(
                id: $hit->product->id,
                slug: $hit->product->slug,
                name: $selected->name,
                shortDescription: $hit->product->shortDescription,
                imageUrl: $hit->product->imageUrl,
                unitAmount: $selected->unitAmount,
                regularUnitAmount: $selected->regularUnitAmount,
                promotionalActive: $selected->promotionalActive,
                currencyCode: $currencyCode,
                currencyDecimalPlaces: $currencyDecimalPlaces,
                available: $selected->available,
                defaultVariantId: $selected->variantId,
                imageSrcset: $hit->product->imageSrcset,
                imageWidth: $hit->product->imageWidth,
                imageHeight: $hit->product->imageHeight,
                url: $selected->path === '' ? $baseUrl : $baseUrl.'/'.$selected->path,
            );
        }, $result->items);

        return new ProductSearchListingData(
            page: new ProductListingPageData($items, $result->total, $page, $perPage),
            criteria: $criteria,
            filters: $result->filters,
        );
    }

    private function decimalFilter(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^\d{1,14}(?:\.\d{1,6})?$/', $value) === 1 ? $value : null;
    }

    /** @return array<string, list<string>> */
    private function attributeFilters(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $result = [];
        foreach (array_slice($input, 0, 12, true) as $definition => $values) {
            if (! is_string($definition) || preg_match('/^[a-z0-9_-]{1,96}$/', $definition) !== 1) {
                continue;
            }
            $values = is_array($values) ? $values : [$values];
            foreach (array_slice($values, 0, 20) as $value) {
                if (is_scalar($value) && mb_strlen((string) $value) <= 191) {
                    $result[$definition][] = (string) $value;
                }
            }
        }

        return $result;
    }
}
