<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure;

use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\ArchetypeResolver;
use Litecommerce\Modules\Cms\Contracts\CmsPageRepository;
use Litecommerce\Modules\Cms\Contracts\ResolvedArchetype;
use Litecommerce\Modules\Cms\Contracts\ResolvedArchetypePage;

/**
 * A merchant's own published override (CmsPage::archetype) always wins over
 * the theme's default — the theme's default itself resolves through the same
 * theme::{...} namespace fallback as blocks/partials (active theme, else
 * default-storefront), and a theme/plugin that registered an archetype
 * without shipping either falls back to a minimal built-in template so the
 * page is never blank or broken.
 */
final class ThemeArchetypeResolver implements ArchetypeResolver
{
    public function __construct(
        private readonly ArchetypeRegistry $registry,
        private readonly CmsPageRepository $pages,
        private readonly ThemeDefaultContentResolver $defaultContent,
    ) {}

    public function resolve(string $slug, string $locale): ?ResolvedArchetypePage
    {
        $resolved = $this->registry->match($slug, $locale);

        return $this->toPage($resolved, $locale);
    }

    public function resolveKey(string $key, string $slug, string $locale): ?ResolvedArchetypePage
    {
        return $this->toPage($this->registry->matchKey($key, $slug, $locale), $locale);
    }

    private function toPage(?ResolvedArchetype $resolved, string $locale): ?ResolvedArchetypePage
    {
        if ($resolved === null) {
            return null;
        }

        $override = $this->pages->findPublishedArchetype($resolved->key, $locale);
        $content = $override === null
            ? $this->defaultContent->resolve("theme::archetypes.{$resolved->key}", ['content' => [['type' => 'archive-heading', 'props' => []]]])
            : $override->content;

        return new ResolvedArchetypePage(
            title: $resolved->match->title,
            content: $content,
            blockContext: [
                'title' => $resolved->match->title,
                'description' => $resolved->match->description,
                'tags' => $resolved->match->tags,
                ...$resolved->match->blockContext,
            ],
        );
    }
}
