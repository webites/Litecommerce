<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Application;

final readonly class SaveBlogCategoryData
{
    public function __construct(
        public string $locale,
        public string $slug,
        public string $name,
        public ?string $description,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {}
}
