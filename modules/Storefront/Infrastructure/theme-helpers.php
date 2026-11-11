<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Litecommerce\Modules\Store\Contracts\AddressFormatter;
use Litecommerce\Modules\Store\Contracts\DateTimeFormatter;
use Litecommerce\Modules\Store\Contracts\MoneyFormatter;
use Litecommerce\Modules\Store\Contracts\PercentageFormatter;
use Litecommerce\Modules\Store\Contracts\PhoneFormatter;
use Litecommerce\Modules\Store\Contracts\QuantityFormatter;
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

if (! function_exists('formatMoney')) {
    /** Customer-facing amount with the decimal places configured for its currency. */
    function formatMoney(string $amount, string $currencyCode, ?string $locale = null): string
    {
        return app(MoneyFormatter::class)->format($amount, $currencyCode, $locale);
    }
}

if (! function_exists('formatMoneyAmount')) {
    function formatMoneyAmount(string $amount, string $currencyCode, int $decimalPlaces, ?string $locale = null): string
    {
        return app(MoneyFormatter::class)->formatWithPlaces($amount, $currencyCode, $decimalPlaces, $locale);
    }
}

if (! function_exists('formatDate')) {
    /** Date in the store time zone and the reader's locale (accepts anything Carbon can parse; null renders as an empty string). */
    function formatDate(DateTimeInterface|string|null $moment, ?string $locale = null): string
    {
        return $moment === null ? '' : app(DateTimeFormatter::class)->date(Carbon::parse($moment), $locale);
    }
}

if (! function_exists('formatDateTime')) {
    function formatDateTime(DateTimeInterface|string|null $moment, ?string $locale = null): string
    {
        return $moment === null ? '' : app(DateTimeFormatter::class)->dateTime(Carbon::parse($moment), $locale);
    }
}

if (! function_exists('formatQuantity')) {
    /** Stored decimal quantity ("2.000000") as "2"; fractional units keep their digits. */
    function formatQuantity(string $quantity, ?string $locale = null): string
    {
        return app(QuantityFormatter::class)->format($quantity, $locale);
    }
}

if (! function_exists('formatPercent')) {
    /** Percentage such as a tax rate ("23.00" renders as "23%"). */
    function formatPercent(string $percentage, ?string $locale = null): string
    {
        return app(PercentageFormatter::class)->format($percentage, $locale);
    }
}

if (! function_exists('formatPhone')) {
    /** Phone number in international notation; an unrecognised value is shown as typed. */
    function formatPhone(string $phone, ?string $countryCode = null): string
    {
        return app(PhoneFormatter::class)->format($phone, $countryCode);
    }
}

if (! function_exists('formatCountry')) {
    function formatCountry(string $countryCode, ?string $locale = null): string
    {
        return app(AddressFormatter::class)->countryName($countryCode, $locale);
    }
}

if (! function_exists('formatAddressLines')) {
    /**
     * @param  array<string, mixed>  $address  address snapshot
     * @return list<string>
     */
    function formatAddressLines(array $address, ?string $locale = null, bool $includePhone = false): array
    {
        return app(AddressFormatter::class)->lines($address, $locale, $includePhone);
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
