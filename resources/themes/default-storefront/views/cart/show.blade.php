@extends('theme::layout')

@section('title', trans('cart::cart.title'))

@section('content')
    <h1>{{ trans('cart::cart.heading') }}</h1>

    {{-- GET /cart loads client-side, so empty vs. non-empty is a data-storefront-when toggle, not server branches. --}}
    <div data-storefront-load="/cart">
        <div class="empty-state" data-storefront-when="empty:lines" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            <h2>{{ trans('cart::cart.empty_title') }}</h2>
            <p>{{ trans('cart::cart.empty_text') }}</p>
            <a class="btn btn-primary" href="{{ route('storefront.catalog.index', ['locale' => $locale]) }}">{{ trans('cart::cart.browse_products') }}</a>
        </div>

        <div class="layout-2col" data-storefront-when="items:lines" hidden>
            <div>
                <div class="card">
                    <table class="cart-table">
                        <thead>
                            <tr><th>{{ trans('cart::cart.table_product') }}</th><th>{{ trans('cart::cart.table_quantity') }}</th><th>{{ trans('cart::cart.table_price') }}</th><th>{{ trans('cart::cart.table_total') }}</th><th></th></tr>
                        </thead>
                        <tbody data-storefront-slot="lines">
                            <template data-storefront-list="lines">
                                <tr data-field-attr="variant_id:data-variant-id">
                                    <td>
                                        <div class="line-product">
                                            <div class="line-thumb" aria-hidden="true">
                                                <img data-field-if="image_url" data-field-attr="image_url:src" alt="" hidden>
                                                <svg data-field-if="!image_url" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                            </div>
                                            <div>
                                                <div class="line-name" data-field="display_name"></div>
                                                <div class="unavailable" data-field-if="!available">{{ trans('cart::cart.unavailable_prefix') }} <span data-field="unavailable_reason"></span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="stepper-sm" data-storefront-stepper>
                                            <button type="button" data-storefront-step="-1" aria-label="{{ trans('cart::cart.decrease_quantity') }}">–</button>
                                            <input
                                                type="text" inputmode="numeric" class="mono" readonly data-field="quantity"
                                                data-storefront-action="put" data-storefront-url="/cart/items/{variant_id}" data-storefront-field="quantity"
                                                aria-label="{{ trans('cart::cart.quantity') }}"
                                            >
                                            <button type="button" data-storefront-step="1" aria-label="{{ trans('cart::cart.increase_quantity') }}">+</button>
                                        </div>
                                    </td>
                                    <td class="mono" data-field="unit_amount_formatted"></td>
                                    <td class="mono" data-field="line_gross_amount_formatted"></td>
                                    <td><button type="button" class="remove-btn" data-storefront-action="delete" data-storefront-url="/cart/items/{variant_id}">{{ trans('cart::cart.remove') }}</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <form class="coupon-form" data-storefront="ajax" action="/cart/coupons" method="post" data-storefront-success-message="{{ trans('cart::cart.coupon_added') }}">
                        <div class="field">
                            <label for="coupon-code">{{ trans('cart::cart.coupon_code_label') }}</label>
                            <input type="text" id="coupon-code" name="code">
                        </div>
                        <div class="field">
                            <label for="coupon-pin">{{ trans('cart::cart.coupon_pin_label') }}</label>
                            <input type="text" id="coupon-pin" name="pin">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm">{{ trans('cart::cart.add_coupon') }}</button>
                        <p data-storefront-message role="status"></p>
                    </form>

                    <ul class="coupon-list" data-storefront-slot="coupons">
                        <template data-storefront-list="coupons">
                            <li data-field-attr="code:data-code">
                                <span><span class="code" data-field="code"></span> (<span data-field="status"></span>) — <span data-field="allocated_amount_formatted"></span></span>
                                <button type="button" class="remove-btn" data-storefront-action="delete" data-storefront-url="/cart/coupons/{code}">{{ trans('cart::cart.remove') }}</button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="card summary">
                <div class="summary-line"><span>{{ trans('cart::cart.subtotal') }}</span><span class="val" data-field="subtotal_gross_amount_formatted"></span></div>
                <div class="summary-line"><span>{{ trans('cart::cart.discount') }}</span><span class="val" data-field="discount_amount_formatted"></span></div>
                <div class="summary-total"><span>{{ trans('cart::cart.total') }}</span><span class="val" data-field="total_gross_amount_formatted"></span></div>
                <a class="btn btn-primary btn-block" style="margin-top: 18px;" href="{{ route('storefront.checkout.show', ['locale' => $locale]) }}">{{ trans('cart::cart.go_to_checkout') }}</a>
            </div>
        </div>
    </div>
@endsection
