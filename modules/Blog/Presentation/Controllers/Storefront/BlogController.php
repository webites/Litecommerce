<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Presentation\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Blog\Contracts\BlogCatalog;
use Litecommerce\Modules\Blog\Contracts\BlogPostData;
use Litecommerce\Modules\Cms\Contracts\ArchetypeResolver;
use Litecommerce\Modules\Cms\Contracts\BlockRenderer;
use Litecommerce\Modules\Cms\Contracts\CmsPageData;
use Litecommerce\Modules\Cms\Contracts\ResolvedArchetypePage;
use Litecommerce\Modules\Redirects\Contracts\RedirectResolver;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;

final class BlogController extends Controller
{
    private const DEFAULT_PER_PAGE = 12;

    public function index(Request $request, string $locale, BlogCatalog $blog, ArchetypeResolver $archetypes, BlockRenderer $blocks, ThemeRenderer $renderer): View
    {
        $archetype = $archetypes->resolveKey('blog', 'blog', $locale);
        abort_if($archetype === null, 404);

        return $this->renderArchive($request, $locale, null, $archetype, $blog, $blocks, $renderer);
    }

    public function category(Request $request, string $locale, string $slug, BlogCatalog $blog, ArchetypeResolver $archetypes, BlockRenderer $blocks, ThemeRenderer $renderer, RedirectResolver $redirects): View|RedirectResponse
    {
        $archetype = $archetypes->resolveKey('blog_category', $slug, $locale);

        if ($archetype === null) {
            $target = $redirects->resolve($locale, "/blog/category/{$slug}");
            if ($target !== null) {
                return redirect("/{$locale}{$target}", 301);
            }
        }

        abort_if($archetype === null, 404);

        $categoryId = is_numeric($archetype->blockContext['blog_category_id'] ?? null)
            ? (int) $archetype->blockContext['blog_category_id']
            : null;
        abort_if($categoryId === null, 404);

        return $this->renderArchive($request, $locale, $categoryId, $archetype, $blog, $blocks, $renderer);
    }

    public function show(string $locale, string $slug, ArchetypeResolver $archetypes, BlockRenderer $blocks, ThemeRenderer $renderer, RedirectResolver $redirects): View|RedirectResponse
    {
        $archetype = $archetypes->resolveKey('blog_post', $slug, $locale);

        if ($archetype === null) {
            $target = $redirects->resolve($locale, "/blog/{$slug}");
            if ($target !== null) {
                return redirect("/{$locale}{$target}", 301);
            }
        }

        abort_if($archetype === null, 404);
        $post = $archetype->blockContext['blog_post'] ?? null;
        abort_unless($post instanceof BlogPostData, 404);

        return $renderer->renderWithDefaultFallback('blog.show', [
            'post' => $post,
            'blocksHtml' => $blocks->render($archetype->content, $locale, $archetype->blockContext),
            'locale' => $locale,
        ]);
    }

    private function renderArchive(Request $request, string $locale, ?int $categoryId, ResolvedArchetypePage $archetype, BlogCatalog $blog, BlockRenderer $blocks, ThemeRenderer $renderer): View
    {
        $pageNumber = max(1, $request->integer('page', 1));
        $perPage = $this->blogPageSize($archetype->content['content']);
        $result = $blog->paginatePublished($locale, $categoryId, $perPage, $pageNumber);
        $context = [
            ...$archetype->blockContext,
            'blog_page' => [
                'items' => $result['items'],
                'total' => $result['total'],
                'current_page' => $pageNumber,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
            ],
        ];

        return $renderer->render('cms.page', [
            'page' => new CmsPageData(
                id: 0,
                slug: $categoryId === null ? 'blog' : 'blog/category/'.(string) $request->route('slug'),
                title: $archetype->title,
                seoTitle: is_string($context['seo_title'] ?? null) ? $context['seo_title'] : null,
                seoDescription: is_string($context['seo_description'] ?? null)
                    ? $context['seo_description']
                    : (is_string($context['description'] ?? null) ? $context['description'] : null),
                content: $archetype->content,
            ),
            'blocksHtml' => $blocks->render($archetype->content, $locale, $context),
            'locale' => $locale,
        ]);
    }

    /** @param list<array<string, mixed>> $blocks */
    private function blogPageSize(array $blocks): int
    {
        return $this->findBlogPageSize($blocks) ?? self::DEFAULT_PER_PAGE;
    }

    /** @param list<array<string, mixed>> $blocks */
    private function findBlogPageSize(array $blocks): ?int
    {
        foreach ($blocks as $block) {
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];

            if (($block['type'] ?? null) === 'blog-post-list') {
                return max(1, min(24, (int) ($props['limit'] ?? self::DEFAULT_PER_PAGE)));
            }

            if (($block['type'] ?? null) !== 'container') {
                continue;
            }

            foreach (['column_1', 'column_2', 'column_3', 'column_4'] as $column) {
                if (is_array($props[$column] ?? null)) {
                    $nested = $this->findBlogPageSize($props[$column]);
                    if ($nested !== null) {
                        return $nested;
                    }
                }
            }
        }

        return null;
    }
}
