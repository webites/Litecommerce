{{--
    Extracted from layout.blade.php's own @auth/@else block, per the same
    reasoning as cart-link.blade.php — registered as the 'account'
    Storefront\Contracts\MenuWidgetRegistry widget. $labelOverride, when
    set, replaces BOTH the logged-in and logged-out label (a menu item has
    exactly one editable label regardless of which branch renders).
--}}
@auth('customer')
    <a href="{{ route('storefront.account.orders.index', ['locale' => $locale ?? '']) }}" class="{{ request()->routeIs('storefront.account.orders.*') ? 'is-active' : '' }}">{{ $labelOverride ?? trans('storefront::messages.nav_my_account') }}</a>
@else
    <a href="{{ route('storefront.account.login', ['locale' => $locale ?? '']) }}">{{ $labelOverride ?? trans('storefront::messages.nav_login') }}</a>
@endauth
