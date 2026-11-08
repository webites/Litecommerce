<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

interface ThemeCatalog
{
    /** @return list<ThemeData> */
    public function all(): array;

    public function find(string $id): ?ThemeData;

    public function active(): ?ThemeData;
}
