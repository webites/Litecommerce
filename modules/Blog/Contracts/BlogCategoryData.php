<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Contracts;

final readonly class BlogCategoryData
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $description,
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
    ) {}
}
