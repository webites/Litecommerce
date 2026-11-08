<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

final readonly class CreatePageData
{
    public function __construct(
        public string $locale,
        public string $slug,
        public string $title,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public string $status,
    ) {}
}
