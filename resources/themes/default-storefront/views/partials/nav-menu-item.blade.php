{{--
    One node of theme::partials.nav-menu's tree ($item is a Storefront\Contracts\
    MenuNodeViewData) — @includes itself for nested submenus, so any depth of
    nesting "just works" without a separate template per level.

    $item->cssClasses (e.g. "sf-menu-item sf-menu-item-widget
    sf-menu-item-widget-cart", or "... sf-menu-item-page sf-menu-item-page-3")
    identify WHAT this item is, WordPress-nav-menu-editor style, so a theme
    or plugin can target one specific item (or every item of one kind) in
    its own CSS without touching PHP.
--}}
<li class="{{ implode(' ', $item->cssClasses) }}" @if (count($item->children) > 0) data-storefront-submenu @endif>
    @if ($item->html !== null)
        {!! $item->html !!}
    @else
        <a href="{{ $item->url }}" @if ($item->targetBlank) target="_blank" rel="noopener" @endif>{{ $item->label }}</a>
    @endif

    @if (count($item->children) > 0)
        <button type="button" data-storefront-submenu-toggle aria-expanded="false" aria-label="{{ trans('storefront::messages.nav_toggle_submenu') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
        </button>
        <ul class="sf-submenu">
            @foreach ($item->children as $child)
                @include('theme::partials.nav-menu-item', ['item' => $child])
            @endforeach
        </ul>
    @endif
</li>
