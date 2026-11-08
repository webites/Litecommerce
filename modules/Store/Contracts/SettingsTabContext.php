<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

final readonly class SettingsTabContext
{
    public function __construct(
        public int $staffAccountId,
        public string $locale,
    ) {}
}
