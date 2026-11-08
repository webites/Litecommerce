<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** What a registered ArchetypeRegistry resolver returns when a slug belongs to it — e.g. a matched category or brand. */
final readonly class ArchetypeMatch
{
    /**
     * @param  array<string, mixed>  $blockContext  Fed into BlockRenderer::render() so context-aware blocks (e.g. product-grid's "current" mode) can resolve "whatever is being viewed" — e.g. ['category_id' => 5].
     * @param  array<string, string>  $tags  Dynamic-tag values for THIS matched instance (e.g. ['category_name' => 'Buty']) — ThemeBlockRenderer substitutes {{tag}} occurrences in any block's text props with these. Advertise the tag NAMES statically via ArchetypeRegistry::register()'s $availableTags so the editor can show merchants what's available, independent of any one instance.
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public array $blockContext,
        public array $tags = [],
    ) {}
}
