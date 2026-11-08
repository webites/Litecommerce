{{--
    $languages/$languageAlternates come from Storefront\Providers\StorefrontServiceProvider's
    theme:: view composer, not any one controller — see RouteAwareLanguageAlternateResolver.
    A language missing from $languageAlternates has no equivalent page, so it
    falls back to that language's own homepage.

    A native <details>/<summary> disclosure, not a row of links: with 5+
    languages configured, a row would either wrap the nav or push other items
    out — a single fixed-width trigger scales to any language count and needs
    no JS to open/close (data-storefront-language-switcher in runtime.js only
    closes it on an outside click, a small polish, not a requirement).
--}}
@if (count($languages ?? []) > 1)
    <details class="sf-language-switcher" data-storefront-language-switcher>
        <summary>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"></path></svg>
            {{ strtoupper($locale ?? '') }}
        </summary>
        <ul>
            @foreach ($languages as $language)
                <li>
                    <a href="{{ $languageAlternates[$language['code']] ?? '/'.$language['code'] }}" class="{{ $language['code'] === ($locale ?? '') ? 'is-active' : '' }}">{{ $language['name'] }}</a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
