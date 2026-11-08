<!doctype html>
{{-- $themeMode is resolved server-side from the storefront_theme cookie, so first paint never flashes the wrong theme. --}}
<html lang="{{ $locale ?? 'en' }}" @if ($themeMode) data-theme="{{ $themeMode }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', getShopName())</title>
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif
    @hasSection('canonical_url')
        <link rel="canonical" href="@yield('canonical_url')">
    @endif
    {{--
        $languageNoindex (Storefront\Providers\StorefrontServiceProvider's
        theme:: composer, from languages.noindex) covers EVERY page under this
        language; a page can additionally opt itself in via
        @section('robots', 'noindex') (e.g. a product's variant_seo_mode) even
        when the language itself is indexable — either one is enough.
    --}}
    @if ($languageNoindex || trim($__env->yieldContent('robots')) === 'noindex')
        <meta name="robots" content="noindex">
    @endif
    {{--
        $languageAlternates/$hreflangSelfPath/$defaultLocaleCode come from
        Storefront\Providers\StorefrontServiceProvider's theme:: view composer
        (same source as the language-switcher partial). Skipped for a
        single-language store, same zero-overhead rule as the switcher. A
        variant deep-link's $hreflangSelfPath/$languageAlternates already
        point at the BASE product URL, not the variant path — a deliberate
        v1 simplification, see docs/knowledge-base/09-presentation.md.
    --}}
    @if (count($languages ?? []) > 1)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ url($hreflangSelfPath) }}">
        @foreach ($languageAlternates as $code => $path)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ url($path) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ url($locale === ($defaultLocaleCode ?? $locale) ? $hreflangSelfPath : ($languageAlternates[$defaultLocaleCode] ?? '/'.$defaultLocaleCode)) }}">
    @endif
    <link rel="stylesheet" href="{{ $themeAssetUrl('css/theme.css') }}">
</head>
<body data-locale="{{ $locale ?? 'en' }}">
    <header class="sf-header" data-storefront-nav>
        <a class="sf-logo" href="{{ url('/'.($locale ?? '')) }}">
            @if (hasLogo())
                @php(showLogo())
            @else
                {{ getShopName() }}
            @endif
        </a>
        @include('theme::partials.nav-menu', ['location' => 'primary', 'mobileToggle' => true])
    </header>

    <main class="sf-main">
        @storefrontSlot('layout.top')
        @yield('content')
    </main>

    <footer class="sf-footer">
        @include('theme::partials.nav-menu', ['location' => 'footer'])
        {{ trans('storefront::messages.footer_tagline') }}
    </footer>

    <script src="{{ asset('storefront/runtime.js') }}"></script>
    <script>window.__i18n = @json($themeTranslations);</script>
    {{-- Self-mounts into the React roots when present; a no-op everywhere else. --}}
    <script src="{{ $themeAssetUrl('js/app.js') }}"></script>
</body>
</html>
