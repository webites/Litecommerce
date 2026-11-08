<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** Storefront-facing read of one published page in one locale — content is Puck's own raw JSON shape ({content, root}), interpreted by Cms\Infrastructure\BlockRenderer, not this contract. */
final readonly class CmsPageData
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public ?string $seoTitle,
        public ?string $seoDescription,
        /** @var array{content: list<array{id?: string, type: string, props: array<string, mixed>}>, root?: array<string, mixed>} */
        public array $content,
    ) {}
}
