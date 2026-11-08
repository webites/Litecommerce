<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** ArchetypeResolver::resolve()'s result — ready to feed straight into BlockRenderer::render($content, $locale, $blockContext). */
final readonly class ResolvedArchetypePage
{
    public function __construct(
        public string $title,
        /** @var array{content: list<array{id?: string, type?: string, props?: array<string, mixed>}>} */
        public array $content,
        /** @var array<string, mixed> */
        public array $blockContext,
    ) {}
}
