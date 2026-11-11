<?php

declare(strict_types=1);

namespace App\Support\I18n;

// Country names come from ext-intl (already a hard hosting requirement, see
// docs/knowledge-base/10-operations.md), not a hand-maintained translation
// file or an extra Composer dependency — Locale::getDisplayRegion() covers
// any ICU locale. Flags are the regional-indicator-symbol Unicode trick
// (U+1F1E6 + letter offset), so no image assets are needed either.
final class CountryDirectory
{
    /** @return list<array{code: string, name: string, flag: string}> */
    public function all(string $locale): array
    {
        /** @var list<string> $codes */
        $codes = array_keys(config('delivery.continents', []));

        $countries = array_map(
            fn (string $code): array => [
                'code' => $code,
                'name' => \Locale::getDisplayRegion("und-{$code}", $locale),
                'flag' => $this->flagFor($code),
            ],
            $codes,
        );

        usort($countries, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $countries;
    }

    /** Localized country name; an unknown code is returned unchanged. */
    public function nameOf(string $code, string $locale): string
    {
        $code = strtoupper(trim($code));
        $name = preg_match('/^[A-Z]{2}$/', $code) === 1 ? \Locale::getDisplayRegion("und-{$code}", $locale) : '';

        return $name === '' || $name === $code ? $code : $name;
    }

    private function flagFor(string $code): string
    {
        $flag = '';

        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(0x1F1E6 + (ord($code[$i]) - ord('A')), 'UTF-8');
        }

        return $flag;
    }
}
