<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts\Events;

final readonly class LanguageProvisioned
{
    public function __construct(public string $code) {}
}
