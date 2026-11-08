<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

final readonly class LanguageData
{
    public function __construct(
        public string $code,
        public string $name,
        public bool $isDefault,
        public bool $isEnabled,
        public bool $noindex,
    ) {}
}
