<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface LanguageRepository
{
    public function default(): ?LanguageData;

    /**
     * @return list<LanguageData>
     */
    public function all(): array;
}
