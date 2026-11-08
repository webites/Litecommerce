<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Catalog\Contracts\ProductSummaryData;
use Litecommerce\Modules\Cms\Contracts\BlockDataRegistry;
use Litecommerce\Modules\Cms\Contracts\BlockRenderer;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Litecommerce\Modules\Media\Contracts\MediaData;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemAssembler;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemData;
use Litecommerce\Modules\Storefront\Contracts\ProductSearchListingBuilder;
use Throwable;

/**
 * Reads only Puck's generic "type"/"props"/"content" keys — nothing
 * Puck-internal — so a future Puck version bump changing its own schema
 * internals never touches this class (see docs/knowledge-base/09-presentation.md).
 * Each block type resolves to theme::blocks.{type}, the same
 * fallback-to-default-theme mechanism as theme::partials.* — a missing
 * view or a render exception is skipped, never breaks the rest of the
 * page (same resilience contract as Storefront\Infrastructure\ConfigDrivenThemeRegionComposer).
 */
final class ThemeBlockRenderer implements BlockRenderer
{
    private const MAX_GRID_LIMIT = 24;

    public function __construct(
        private readonly ProductCatalog $products,
        private readonly ProductListingItemAssembler $assembler,
        private readonly CurrencyRepository $currencies,
        private readonly MediaLibrary $media,
        private readonly GlobalTagRegistry $globalTags,
        private readonly BlockDataRegistry $blockData,
        private readonly ProductSearchListingBuilder $searchListings,
        private readonly Request $request,
    ) {}

    public function render(array $content, string $locale, array $context = []): string
    {
        // Global tags resolve for EVERY render (plain page or archetype); a
        // same-named archetype tag (e.g. from ArchetypeMatch::tags) wins over
        // a global one, since it's merged in second below.
        $contextTags = is_array($context['tags'] ?? null) ? $context['tags'] : [];
        $context['tags'] = [...$this->globalTags->resolve($locale), ...$contextTags];

        return $this->renderBlocks($content['content'] ?? [], $locale, $context);
    }

    /**
     * Recurses into 'container' blocks' own slot props (column_1..column_4,
     * each itself a list<{type, props}> — Puck's "slot" field stores nested
     * blocks in exactly the same shape as the top-level list) so grid/flex
     * containers can hold any other block, including another container.
     * $context passes down unchanged — it describes the page as a whole
     * (e.g. "which category is being viewed"), not any one block.
     *
     * @param  list<array{id?: string, type?: string, props?: array<string, mixed>}>  $blocks
     * @param  array<string, mixed>  $context
     */
    private function renderBlocks(array $blocks, string $locale, array $context): string
    {
        $html = '';

        $tags = is_array($context['tags'] ?? null) ? $context['tags'] : [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $props = $this->interpolateTags($props, $tags);

            if (! is_string($type) || $type === '') {
                continue;
            }

            $view = "theme::blocks.{$type}";

            if (! View::exists($view)) {
                continue;
            }

            try {
                $inner = View::make($view, $this->viewData($type, $props, $locale, $context))->render();
                $width = ($props['width'] ?? 'contained') === 'full' ? 'full' : 'contained';
                $html .= "<div class=\"cms-block cms-block--{$width}\">{$inner}</div>";
            } catch (Throwable $exception) {
                Log::warning("Cms block [{$type}] failed to render.", ['exception' => $exception]);
            }
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function viewData(string $type, array $props, string $locale, array $context): array
    {
        $data = match ($type) {
            // CSS background-image, not an <img> — no srcset equivalent worth the
            // complexity of image-set() here, plain URL is enough.
            'hero' => ['backgroundUrl' => $this->resolveImage($props['background'] ?? null)?->url],
            'image-banner' => $this->imageBannerData($props),
            'product-grid' => $this->productGridData($props, $locale, $context),
            'container' => ['columns' => array_map(
                fn (mixed $items): string => $this->renderBlocks(is_array($items) ? $items : [], $locale, $context),
                [$props['column_1'] ?? [], $props['column_2'] ?? [], $props['column_3'] ?? [], $props['column_4'] ?? []],
            )],
            'video' => ['youtubeId' => $this->resolveYoutubeId(is_string($props['url'] ?? null) ? $props['url'] : '')],
            'archive-heading' => ['title' => $context['title'] ?? null, 'description' => $context['description'] ?? null],
            'blog-post-list' => ['blogPage' => is_array($context['blog_page'] ?? null) ? $context['blog_page'] : null],
            default => [],
        };

        return [
            'props' => $props,
            'locale' => $locale,
            ...$data,
            ...$this->blockData->resolve($type, $props, $locale, $context),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function productGridData(array $props, string $locale, array $context): array
    {
        $mode = is_string($props['mode'] ?? null) ? $props['mode'] : 'category';
        $categoryId = is_numeric($context['category_id'] ?? null) ? (int) $context['category_id'] : null;

        if ($mode === 'current' && $categoryId !== null) {
            $listing = $this->searchListings->build($locale, $categoryId, $this->request->query());

            return [
                'products' => $listing->page->items,
                'catalogListing' => $listing,
                'categoryId' => $categoryId,
                'catalogFilterAction' => $this->request->url(),
                'catalogClearUrl' => $this->request->url(),
            ];
        }

        return ['products' => $this->resolveProducts($props, $locale, $context)];
    }

    /**
     * Substitutes {{tag_name}} occurrences (e.g. {{category_name}}) in every
     * string prop — including nested ones, such as a container's own
     * column_1..4 block lists or a list block's items — with the matching
     * archetype's resolved value. Recursing into nested block definitions
     * here is harmless: each nested block gets interpolated AGAIN with its
     * own props when renderBlocks() reaches it, and replacement values never
     * themselves contain "{{", so re-running is a no-op. An unrecognised tag
     * (typo, or used on the wrong archetype) resolves to an empty string —
     * never literal "{{...}}" leaking onto the storefront. Outside any
     * archetype context (a plain Cms page), $tags is empty and this is a
     * pure no-op.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, string>  $tags
     * @return array<string, mixed>
     */
    private function interpolateTags(array $props, array $tags): array
    {
        if ($tags === []) {
            return $props;
        }

        return array_map(fn (mixed $value): mixed => $this->interpolateValue($value, $tags), $props);
    }

    /** @param array<string, string> $tags */
    private function interpolateValue(mixed $value, array $tags): mixed
    {
        if (is_string($value)) {
            return preg_replace_callback(
                '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
                static fn (array $matches): string => $tags[$matches[1]] ?? '',
                $value,
            );
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->interpolateValue($item, $tags), $value);
        }

        return $value;
    }

    /**
     * Deliberately YouTube-only (not a generic multi-provider oEmbed system —
     * see docs/knowledge-base/09-presentation.md's Gutenberg research note).
     * Supports youtube.com/watch?v=, youtu.be/ and youtube.com/embed/ URLs.
     */
    private function resolveYoutubeId(string $url): ?string
    {
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /** @return array{imageUrl: ?string, imageSrcset: ?string, imageWidth: ?int, imageHeight: ?int} */
    private function imageBannerData(array $props): array
    {
        $image = $this->resolveImage($props['image'] ?? null);

        return [
            'imageUrl' => $image?->url,
            'imageSrcset' => $image?->srcset(),
            'imageWidth' => $image?->width,
            'imageHeight' => $image?->height,
        ];
    }

    private function resolveImage(mixed $image): ?MediaData
    {
        $mediaId = is_array($image) ? ($image['media_id'] ?? null) : null;

        if (! is_numeric($mediaId)) {
            return null;
        }

        return $this->media->find((int) $mediaId);
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $context
     * @return list<ProductListingItemData>
     */
    private function resolveProducts(array $props, string $locale, array $context): array
    {
        $mode = is_string($props['mode'] ?? null) ? $props['mode'] : 'category';
        $limit = max(1, min(self::MAX_GRID_LIMIT, (int) ($props['limit'] ?? 8)));
        $baseCurrency = $this->currencies->base();
        $currencyCode = $baseCurrency === null ? 'PLN' : $baseCurrency->code;

        $items = match ($mode) {
            'featured' => $this->products->paginateFeatured($locale, $limit, 1)['items'],
            'manual' => $this->products->findManyPublished($this->manualProductIds($props), $locale),
            // Outside an archetype page (e.g. a plain Cms page reusing this
            // block), $context carries neither key — an empty grid, never a crash.
            'current' => $this->resolveCurrentContextProducts($context, $locale, $limit),
            default => $this->products->paginatePublished($locale, $this->categoryId($props), $limit, 1)['items'],
        };

        return $this->assembler->assemble($items, $currencyCode);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<ProductSummaryData>
     */
    private function resolveCurrentContextProducts(array $context, string $locale, int $limit): array
    {
        if (is_numeric($context['category_id'] ?? null)) {
            return $this->products->paginatePublished($locale, (int) $context['category_id'], $limit, 1)['items'];
        }

        if (is_numeric($context['brand_id'] ?? null)) {
            return $this->products->paginateByBrand($locale, (int) $context['brand_id'], $limit, 1)['items'];
        }

        return [];
    }

    /** @param array<string, mixed> $props */
    private function categoryId(array $props): ?int
    {
        $value = $props['category_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return list<int>
     */
    private function manualProductIds(array $props): array
    {
        $items = is_array($props['product_ids'] ?? null) ? $props['product_ids'] : [];

        return array_values(array_filter(array_map(
            static fn (mixed $item): ?int => is_array($item) && isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null,
            $items,
        )));
    }
}
