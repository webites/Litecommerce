<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/**
 * Interprets a page's stored Puck JSON (CmsPageData::content) into theme
 * HTML — the only way Storefront's page-rendering controllers turn page
 * content into markup, so they never depend on Cms's Infrastructure
 * directly (see tests/Architecture/ModuleBoundaryTest.php).
 */
interface BlockRenderer
{
    /**
     * @param  array{content?: list<array{id?: string, type?: string, props?: array<string, mixed>}>}  $content
     * @param  array<string, mixed>  $context  Facts about "what's currently being viewed" for archetype pages (e.g. ['title' => ..., 'category_id' => 5]) — context-aware blocks (e.g. product-grid's "current" mode, archive-heading) read it; every other block ignores it. Empty for a plain Cms page.
     */
    public function render(array $content, string $locale, array $context = []): string;
}
