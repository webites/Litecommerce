<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Contracts;

final readonly class BlogPostData
{
    /** @param list<BlogCategoryData> $categories */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public ?string $excerpt,
        public string $contentHtml,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public ?string $publishedAt,
        public ?string $featuredImageUrl,
        public array $categories = [],
        public ?string $featuredImageSrcset = null,
        public ?int $featuredImageWidth = null,
        public ?int $featuredImageHeight = null,
    ) {}
}
