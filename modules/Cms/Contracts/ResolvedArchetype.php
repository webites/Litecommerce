<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** ArchetypeRegistry::match()'s result — which registered archetype claimed the slug, plus what it resolved to. */
final readonly class ResolvedArchetype
{
    public function __construct(
        public string $key,
        public ArchetypeMatch $match,
    ) {}
}
