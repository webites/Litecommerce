<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Application;

use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

/** Picks the enabled store language a consent statement is shown and recorded in. */
final class ConsentLocaleResolver
{
    public function __construct(private readonly LanguageRepository $languages) {}

    public function resolve(?string $requested): string
    {
        $enabled = array_values(array_filter($this->languages->all(), static fn (LanguageData $language): bool => $language->isEnabled));

        foreach ($enabled as $language) {
            if ($language->code === $requested) {
                return $language->code;
            }
        }

        $default = $this->languages->default();

        return $default === null ? (string) config('app.fallback_locale', 'en') : $default->code;
    }
}
