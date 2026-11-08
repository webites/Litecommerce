<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Contracts;

interface DemoDataContent
{
    /**
     * Loads one translation group (for example "catalog::demo") for the language the demo data is installed in.
     * Languages without their own file fall back to the application fallback locale.
     *
     * @return array<string, mixed>
     */
    public function load(string $translationGroup, string $locale): array;
}
