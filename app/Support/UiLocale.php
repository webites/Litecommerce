<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

/** The language of the panel, login screen and installer: kept in the session and mirrored in a plain preference cookie. */
final class UiLocale
{
    public const SESSION_KEY = 'panel_locale';

    public const COOKIE = 'ui_locale';

    /** @return list<string> */
    public static function supported(): array
    {
        return array_values((array) config('app.supported_ui_locales'));
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::supported(), true);
    }

    public static function cookie(string $locale): Cookie
    {
        return cookie(self::COOKIE, $locale, 60 * 24 * 365);
    }
}
