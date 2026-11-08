<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Application;

use Litecommerce\Modules\Blog\Contracts\BlogStatus;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategory;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategoryTranslation;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPost;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPostTranslation;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContent;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContext;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;

final readonly class BlogDemoDataContributor implements DemoDataContributor
{
    /** Post key => category key, image asset. */
    private const POSTS = [
        'ceramic_care' => ['guide', 'ceramic-mug'],
        'linen_kitchen' => ['materials', 'linen-apron'],
        'oiling_wood' => ['guide', 'oak-tray'],
        'small_office_light' => ['organization', 'desk-lamp'],
        'notes_system' => ['organization', 'notebook'],
        'burning_candle' => ['guide', 'soy-candle'],
        'weekend_table' => ['inspiration', 'oak-tray'],
        'useful_gift' => ['gifts', 'soy-candle'],
        'mug_story' => ['workshop', 'ceramic-mug'],
        'apron_story' => ['workshop', 'linen-apron'],
        'evening_light' => ['inspiration', 'desk-lamp'],
        'paper_vs_app' => ['organization', 'notebook'],
    ];

    public function __construct(
        private SaveBlogCategory $saveCategory,
        private SaveBlogPost $savePost,
        private MediaLibrary $media,
        private DemoDataContent $demoContent,
    ) {}

    public function key(): string
    {
        return 'blog';
    }

    public function priority(): int
    {
        return 500;
    }

    public function install(DemoDataContext $context, DemoDataResourceRegistry $resources): void
    {
        $content = $this->demoContent->load('blog::demo', $context->locale);

        $categories = [];
        foreach ($content['categories'] as $key => $definition) {
            $category = $this->saveCategory->handle(null, new SaveBlogCategoryData(
                locale: $context->locale,
                slug: $this->categorySlug($definition['slug'], $context),
                name: $definition['name'],
                description: $definition['description'],
                seoTitle: $definition['name'],
                seoDescription: $definition['description'],
            ));
            $categories[$key] = (int) $category->getKey();
            $resources->record($context->batchId, $this->key(), 'category', (int) $category->getKey());
        }

        $index = 0;
        foreach (self::POSTS as $postKey => [$categoryKey, $asset]) {
            $definition = $content['posts'][$postKey];
            $mediaId = $this->mediaId($context, $resources, $asset);
            $imageUrl = $this->media->find($mediaId)?->url;
            if ($imageUrl === null) {
                throw new \RuntimeException("Obraz {$asset} nie ma publicznego adresu URL.");
            }
            $imagePath = parse_url($imageUrl, PHP_URL_PATH);
            if (! is_string($imagePath) || ! str_starts_with($imagePath, '/storage/')) {
                throw new \RuntimeException("Obraz {$asset} nie ma lokalnego adresu publicznego.");
            }

            $post = $this->savePost->handle(null, new SaveBlogPostData(
                locale: $context->locale,
                slug: $this->postSlug($definition['slug'], $context),
                title: $definition['title'],
                excerpt: $definition['excerpt'],
                contentHtml: $this->content($definition, $imagePath, $content['templates']),
                seoTitle: $definition['title'],
                seoDescription: $definition['excerpt'],
                status: BlogStatus::Published->value,
                publishAt: now()->subDays((++$index) * 3)->toDateTimeString(),
                categoryIds: [$categories[$categoryKey]],
            ));
            $post->update(['featured_media_id' => $mediaId]);
            $resources->record($context->batchId, $this->key(), 'post', (int) $post->getKey());
        }
    }

    public function remove(string $batchId, DemoDataResourceRegistry $resources): void
    {
        BlogPost::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'post')))->delete();
        BlogCategory::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'category')))->delete();
    }

    /**
     * @param  array{intro:string,heading:string,paragraph:string,bullets:list<string>,steps:list<string>,quote:string,title:string}  $definition
     * @param  array{steps_heading:string,closing_heading:string,closing_text:string}  $templates
     */
    private function content(array $definition, string $imageUrl, array $templates): string
    {
        $bullets = implode('', array_map(static fn (string $item): string => '<li>'.htmlspecialchars($item, ENT_QUOTES).'</li>', $definition['bullets']));
        $steps = implode('', array_map(static fn (string $item): string => '<li>'.htmlspecialchars($item, ENT_QUOTES).'</li>', $definition['steps']));

        return '<p>'.$definition['intro'].'</p>'
            .'<h2>'.$definition['heading'].'</h2><p>'.$definition['paragraph'].'</p>'
            .'<ul>'.$bullets.'</ul>'
            .'<img src="'.htmlspecialchars($imageUrl, ENT_QUOTES).'" alt="'.htmlspecialchars($definition['title'], ENT_QUOTES).'" loading="lazy">'
            .'<h2>'.$templates['steps_heading'].'</h2><ol>'.$steps.'</ol>'
            .'<blockquote>'.$definition['quote'].'</blockquote>'
            .'<h2>'.$templates['closing_heading'].'</h2><p>'.$templates['closing_text'].'</p>';
    }

    private function mediaId(DemoDataContext $context, DemoDataResourceRegistry $resources, string $asset): int
    {
        $ids = $resources->ids($context->batchId, 'media', "asset.{$asset}");
        if (count($ids) !== 1) {
            throw new \RuntimeException("Brakuje obrazu demonstracyjnego: {$asset}.");
        }

        return (int) $ids[0];
    }

    private function categorySlug(string $base, DemoDataContext $context): string
    {
        return BlogCategoryTranslation::query()->where('locale', $context->locale)->where('slug', $base)->exists()
            ? $base.'-demo-'.strtolower(substr($context->batchId, -6))
            : $base;
    }

    private function postSlug(string $base, DemoDataContext $context): string
    {
        return BlogPostTranslation::query()->where('locale', $context->locale)->where('slug', $base)->exists()
            ? $base.'-demo-'.strtolower(substr($context->batchId, -6))
            : $base;
    }

    /** @param list<string> $ids @return list<int> */
    private function integers(array $ids): array
    {
        return array_map('intval', $ids);
    }
}
