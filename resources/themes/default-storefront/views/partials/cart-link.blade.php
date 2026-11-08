{{--
    Extracted from layout.blade.php's own inline markup so it can also be
    registered as the 'cart' Storefront\Contracts\MenuWidgetRegistry widget
    (see StorefrontServiceProvider::boot()) — a menu item, not just a
    hardcoded nav entry. $labelOverride is set only when rendered through
    that registry with a merchant-chosen label; plain @include callers
    (none left after the menu feature, kept for direct reuse) get the
    default translation.
--}}
<a href="{{ route('storefront.cart.show', ['locale' => $locale ?? '']) }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
    {{ $labelOverride ?? trans('storefront::messages.nav_cart') }} <span data-storefront="cart-count">0</span>
</a>
