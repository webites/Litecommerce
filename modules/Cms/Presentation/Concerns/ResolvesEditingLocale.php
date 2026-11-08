<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

/**
 * Shared by PageController/ArchetypeTemplateController and their Save*Request
 * classes — the page builder's language switcher (Panel/Cms/Edit.jsx) sends
 * the locale being edited/saved as ?locale=, clamped to the store's actually
 * enabled languages so a stale or forged code never falls through to an
 * unexpected translation row.
 */
trait ResolvesEditingLocale
{
    private function defaultLocale(LanguageRepository $languages): string
    {
        $language = $languages->default();

        return $language === null ? (string) config('app.locale', 'pl') : $language->code;
    }

    private function resolveRequestedLocale(string $requested, LanguageRepository $languages): string
    {
        $default = $this->defaultLocale($languages);

        if ($requested === '') {
            return $default;
        }

        $enabledCodes = array_map(
            static fn (LanguageData $language): string => $language->code,
            array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled)),
        );

        return in_array($requested, $enabledCodes, true) ? $requested : $default;
    }

    /**
     * @param  Collection<int, CmsPageTranslation>  $translations
     * @return list<array{code: string, name: string, hasTranslation: bool}>
     */
    private function languageSwitcherOptions(LanguageRepository $languages, Collection $translations): array
    {
        $translatedCodes = $translations->pluck('locale')->all();

        return array_map(
            static fn (LanguageData $language): array => [
                'code' => $language->code,
                'name' => $language->name,
                'hasTranslation' => in_array($language->code, $translatedCodes, true),
            ],
            array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled)),
        );
    }
}
