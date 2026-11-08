<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Presentation\Concerns;

use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

trait ResolvesBlogLocale
{
    private function defaultLocale(LanguageRepository $languages): string
    {
        $default = $languages->default();

        return $default === null ? (string) config('app.locale', 'en') : $default->code;
    }

    private function requestedLocale(string $requested, LanguageRepository $languages): string
    {
        $default = $this->defaultLocale($languages);
        $enabled = array_map(
            static fn (LanguageData $language): string => $language->code,
            array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled)),
        );

        return in_array($requested, $enabled, true) ? $requested : $default;
    }

    /** @return list<array{code:string,name:string,isDefault:bool,isEnabled:bool}> */
    private function languageOptions(LanguageRepository $languages): array
    {
        return array_map(static fn (LanguageData $language): array => [
            'code' => $language->code,
            'name' => $language->name,
            'isDefault' => $language->isDefault,
            'isEnabled' => $language->isEnabled,
        ], array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled)));
    }
}
