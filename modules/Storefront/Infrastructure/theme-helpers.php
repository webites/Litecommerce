<?php

declare(strict_types=1);

use Litecommerce\Modules\Store\Contracts\StoreSettingsData;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

/**
 * Global template tags for theme views, WordPress-style get_X()/show_X()
 * pairs backed by Store\Contracts\StoreSettingsReader. Required once from
 * StorefrontServiceProvider::register() (every request, module is always
 * enabled) — themes call these directly instead of reading $storeSettings
 * (the same data, still shared by the theme:: view composer for BC/direct
 * property access) themselves.
 */
if (! function_exists('storeSettings')) {
    function storeSettings(): StoreSettingsData
    {
        return app(StoreSettingsReader::class)->current();
    }
}

if (! function_exists('getShopName')) {
    function getShopName(): string
    {
        return storeSettings()->storeName;
    }
}

if (! function_exists('showShopName')) {
    function showShopName(): void
    {
        echo e(getShopName());
    }
}

if (! function_exists('hasLogo')) {
    function hasLogo(): bool
    {
        return getLogo() !== null;
    }
}

if (! function_exists('getLogo')) {
    function getLogo(): ?string
    {
        return storeSettings()->logoUrl;
    }
}

if (! function_exists('showLogo')) {
    /**
     * Renders nothing when no logo is set — pair with hasLogo() for a
     * text fallback, the same way WordPress themes guard the_custom_logo().
     *
     * @param  array<string, string>  $attributes  extra HTML attributes merged onto the <img> tag (e.g. class) — take precedence over the computed defaults
     */
    function showLogo(array $attributes = []): void
    {
        $url = getLogo();

        if ($url === null) {
            return;
        }

        $settings = storeSettings();
        $attributes += ['alt' => getShopName()];

        showImage($url, $settings->logoSrcset, null, $settings->logoWidth, $settings->logoHeight, $attributes);
    }
}

if (! function_exists('isBlogEnabled')) {
    function isBlogEnabled(): bool
    {
        return storeSettings()->blogEnabled;
    }
}

if (! function_exists('formatMoneyAmount')) {
    function formatMoneyAmount(string $amount, string $currencyCode, int $decimalPlaces, ?string $locale = null): string
    {
        $decimalPlaces = max(0, min(6, $decimalPlaces));
        $normalized = bcadd($amount, '0', $decimalPlaces);
        [$integer, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $isPolish = ($locale ?? app()->getLocale()) === 'pl';
        $groupSeparator = $isPolish ? "\u{00A0}" : ',';
        $decimalSeparator = $isPolish ? ',' : '.';
        $sign = str_starts_with($integer, '-') ? '-' : '';
        $digits = ltrim($integer, '-');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', $groupSeparator, $digits) ?? $digits;
        $formatted = $sign.$grouped;

        if ($decimalPlaces > 0) {
            $formatted .= $decimalSeparator.str_pad($fraction, $decimalPlaces, '0');
        }

        return $formatted.' '.$currencyCode;
    }
}

if (! function_exists('showImage')) {
    /**
     * ONE general <img> tag helper for every Media-backed image in the system (product
     * cards/gallery, blog featured images, CMS image banners, logo — not a per-place
     * concern), so templates never hand-roll conditional srcset/width/height attributes
     * themselves. Renders nothing when $url is null (e.g. a product/post with no image —
     * callers keep their own fallback markup around the call for that case).
     *
     * $sizes only makes sense paired with $srcset and can't be inferred generically —
     * it must reflect each call site's own CSS layout (see the theme's theme.css).
     *
     * @param  array<string, string>  $attributes  extra HTML attributes (e.g. alt, class, loading) — take precedence over the computed src/srcset/sizes/width/height
     */
    function showImage(?string $url, ?string $srcset = null, ?string $sizes = null, ?int $width = null, ?int $height = null, array $attributes = []): void
    {
        if ($url === null) {
            return;
        }

        $defaults = ['src' => $url];

        if ($srcset !== null) {
            $defaults['srcset'] = $srcset;

            if ($sizes !== null) {
                $defaults['sizes'] = $sizes;
            }
        }
        if ($width !== null) {
            $defaults['width'] = (string) $width;
        }
        if ($height !== null) {
            $defaults['height'] = (string) $height;
        }

        $attributes += $defaults;
        $attributeString = implode(' ', array_map(
            static fn (string $key, string $value): string => e($key).'="'.e($value).'"',
            array_keys($attributes),
            $attributes,
        ));

        echo '<img '.$attributeString.'>';
    }
}
