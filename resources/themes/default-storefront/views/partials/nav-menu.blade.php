{{--
    $navMenus comes from Storefront\Providers\StorefrontServiceProvider's
    theme:: view composer — see Storefront\Infrastructure\RouteAwareMenuResolver.
    Renders nothing when no menu is assigned to $location (or it resolved to
    zero visible items) — same "renders nothing when unconfigured" contract
    as theme-mode-toggle.blade.php. The whole menu is configured in the
    panel; there is deliberately no code-level default content here.

    The "sf-menu-<location>" class on the <ul> is a plain CSS hook (like
    WordPress's own per-location menu class) so a theme/plugin can style one
    location's menu differently without touching PHP — see also each <li>'s
    own classes in nav-menu-item.blade.php.

    $mobileToggle (bool, default false) — pass true ONLY for the one location
    that should get the mobile hamburger drawer (the header's 'primary' menu;
    'footer' never does, it's a short flat list at the bottom of the page,
    not a sticky nav needing to be hidden/shown). The hamburger button,
    backdrop, and <nav> wrapper live in THIS partial (not layout.blade.php)
    specifically so they share the exact same "$items is empty" check — no
    menu configured means no hamburger either, never an empty drawer.
--}}
@php($items = $navMenus[$location] ?? [])
@if (count($items) > 0)
    @if ($mobileToggle ?? false)
        <button type="button" class="sf-nav-toggle" data-storefront-nav-toggle aria-expanded="false" aria-controls="sf-{{ $location }}-nav" aria-label="{{ trans('storefront::messages.nav_toggle_menu') }}">
            <span class="sf-nav-toggle-bar"></span>
            <span class="sf-nav-toggle-bar"></span>
            <span class="sf-nav-toggle-bar"></span>
        </button>
        <div class="sf-nav-backdrop" data-storefront-nav-close aria-hidden="true"></div>
        <nav class="sf-nav" id="sf-{{ $location }}-nav">
            <ul class="sf-menu sf-menu-{{ $location }}" data-storefront-menu>
                @foreach ($items as $item)
                    @include('theme::partials.nav-menu-item', ['item' => $item])
                @endforeach
            </ul>
        </nav>
    @else
        <ul class="sf-menu sf-menu-{{ $location }}" data-storefront-menu>
            @foreach ($items as $item)
                @include('theme::partials.nav-menu-item', ['item' => $item])
            @endforeach
        </ul>
    @endif
@endif
