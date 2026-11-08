<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

final readonly class UpdateArchetypeTemplateData
{
    public function __construct(
        public string $locale,
        public string $title,
    ) {}
}
