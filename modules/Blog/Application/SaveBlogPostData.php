<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Application;

final readonly class SaveBlogPostData
{
    /** @param list<int> $categoryIds */
    public function __construct(
        public string $locale,
        public string $slug,
        public string $title,
        public ?string $excerpt,
        public string $contentHtml,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public string $status,
        public ?string $publishAt,
        public array $categoryIds,
    ) {}
}
