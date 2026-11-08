<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Application;

final readonly class SaveRedirectData
{
    public function __construct(
        public string $locale,
        public string $fromPath,
        public string $toPath,
    ) {}
}
