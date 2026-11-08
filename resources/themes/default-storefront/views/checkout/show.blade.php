@extends('theme::layout')

@section('title', trans('cart::checkout.title'))

@section('content')
    <p class="crumb"><a href="{{ route('storefront.cart.show', ['locale' => $locale]) }}">{{ trans('cart::checkout.back_to_cart') }}</a></p>
    <h1>{{ trans('cart::checkout.heading') }}</h1>

    {{-- Bootstraps (or resumes) the current cart's checkout draft, exposing its id as data-draft for the {draft} tokens below. --}}
    <div data-storefront-init="post" data-storefront-url="/checkout" data-storefront-expose="id:draft">
        <div class="layout-2col">
            <form id="checkout-address-form" class="card form-card" data-storefront="ajax" data-storefront-method="put" data-storefront-url="/checkout/{draft}"
                  data-storefront-success-message="{{ trans('cart::checkout.address_saved') }}">
                <h3 class="section-h">{{ trans('cart::checkout.section_title') }}</h3>
                <p class="section-sub">{{ trans('cart::checkout.section_sub') }}</p>

                <div class="segmented" role="group" aria-label="{{ trans('cart::checkout.buyer_type_group_label') }}">
                    <label><input type="radio" name="buyer_type" value="person" checked><span>{{ trans('cart::checkout.buyer_type_person') }}</span></label>
                    <label><input type="radio" name="buyer_type" value="company"><span>{{ trans('cart::checkout.buyer_type_company') }}</span></label>
                </div>

                <div class="field-row" data-storefront-show-if="buyer_type:company" hidden>
                    <div class="field"><label>{{ trans('cart::checkout.company_name_label') }}</label><input name="company_name"></div>
                    <div class="field"><label>{{ trans('cart::checkout.tax_id_label') }}</label><input name="tax_identifier"></div>
                </div>

                <div class="field-row">
                    <div class="field"><label>{{ trans('cart::checkout.email_label') }}</label><input type="email" name="email" required></div>
                    <div class="field"><label>{{ trans('cart::checkout.phone_label') }}</label><input name="phone"></div>
                </div>

                <fieldset>
                    <legend>{{ trans('cart::checkout.billing_address_legend') }}</legend>
                    <div class="field"><label>{{ trans('cart::checkout.recipient_name_label') }}</label><input name="billing_address[recipient_name]"></div>
                    <div class="field-row">
                        <div class="field"><label>{{ trans('cart::checkout.country_code_label') }}</label>@include('theme::partials.country-select', ['fieldName' => 'billing_address[country_code]'])</div>
                        <div class="field"><label>{{ trans('cart::checkout.postal_code_label') }}</label><input name="billing_address[postal_code]"></div>
                    </div>
                    <div class="field"><label>{{ trans('cart::checkout.city_label') }}</label><input name="billing_address[city]"></div>
                    <div class="field"><label>{{ trans('cart::checkout.address_label') }}</label><input name="billing_address[address_line_1]"></div>
                    <div class="field"><label>{{ trans('cart::checkout.address_line_2_label') }}</label><input name="billing_address[address_line_2]"></div>
                    <div class="field"><label>{{ trans('cart::checkout.phone_label') }}</label><input name="billing_address[phone]"></div>
                </fieldset>

                <label class="check-row">
                    <input type="checkbox" name="ships_to_different_address"> {{ trans('cart::checkout.ships_to_different_address_label') }}
                </label>

                <div data-storefront-show-if="ships_to_different_address:true" hidden>
                    <fieldset>
                        <legend>{{ trans('cart::checkout.shipping_address_legend') }}</legend>
                        <div class="field"><label>{{ trans('cart::checkout.recipient_name_label') }}</label><input name="shipping_address[recipient_name]"></div>
                        <div class="field-row">
                            <div class="field"><label>{{ trans('cart::checkout.country_code_label') }}</label>@include('theme::partials.country-select', ['fieldName' => 'shipping_address[country_code]'])</div>
                            <div class="field"><label>{{ trans('cart::checkout.postal_code_label') }}</label><input name="shipping_address[postal_code]"></div>
                        </div>
                        <div class="field"><label>{{ trans('cart::checkout.city_label') }}</label><input name="shipping_address[city]"></div>
                        <div class="field"><label>{{ trans('cart::checkout.address_label') }}</label><input name="shipping_address[address_line_1]"></div>
                        <div class="field"><label>{{ trans('cart::checkout.address_line_2_label') }}</label><input name="shipping_address[address_line_2]"></div>
                        <div class="field"><label>{{ trans('cart::checkout.phone_label') }}</label><input name="shipping_address[phone]"></div>
                    </fieldset>
                </div>

                <div class="field"><label>{{ trans('cart::checkout.notes_label') }}</label><textarea name="notes"></textarea></div>

                <label class="check-row">
                    <input type="checkbox" name="marketing_consent"> {{ trans('consents::marketing.statement') }}
                </label>

                {{-- Delivery + pickup point are rendered in the delivery card but belong to this form via form="". --}}
                <input type="hidden" name="delivery_pickup_point_id" form="checkout-address-form">

                <button type="submit" class="btn btn-primary" style="margin-top: 20px;">{{ trans('cart::checkout.recalculate_button') }}</button>
                <p data-storefront-message role="status"></p>
            </form>

            <div>
                <div class="card form-card" data-storefront-load="/checkout/{draft}" style="margin-bottom: 20px;">
                    <h3 class="section-h">{{ trans('cart::checkout.delivery_title') }}</h3>
                    <p class="section-sub">{{ trans('cart::checkout.delivery_sub') }}</p>
                    <div data-storefront-slot="price.delivery.options">
                        <template data-storefront-list="price.delivery.options" data-storefront-list-kind="delivery-options">
                            <label class="radio-card">
                                <input type="radio" name="delivery_method_id" form="checkout-address-form"
                                       data-field="method_id" data-field-attr="pickup_point_id:data-pickup-point-id"
                                       data-storefront-auto-submit data-storefront-preselect>
                                <span class="left"><span class="radio-dot"></span><span data-field="label"></span></span>
                                <span class="price mono" data-field="gross_amount"></span>
                            </label>
                        </template>
                    </div>

                    {{-- Belongs to checkout-confirm-form below even though it is filled here, where the price (and its hash) is actually loaded from. --}}
                    <input type="hidden" name="price_hash" form="checkout-confirm-form" data-field="price.hash">
                </div>

                <div class="card form-card" data-storefront-load="/checkout/{draft}/payment-methods" style="margin-bottom: 20px;">
                    <h3 class="section-h">{{ trans('cart::checkout.payment_title') }}</h3>
                    <p class="section-sub" data-storefront-when="empty:methods" hidden>{{ trans('cart::checkout.no_payment_methods') }}</p>
                    <div data-storefront-slot="methods">
                        <template data-storefront-list="methods">
                            <label class="radio-card">
                                <input type="radio" name="payment_method_id" form="checkout-confirm-form" data-field="id" data-storefront-preselect>
                                <span class="left"><span class="radio-dot"></span><span data-field="name"></span></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Own independent GET /checkout/{draft} — a data-storefront-load region only fills its own descendants, so this can't nest inside the delivery card above without its data-field clones colliding. --}}
                <form id="checkout-confirm-form" class="card summary" data-storefront="ajax" action="/checkout/{draft}/confirm" method="post"
                      data-storefront-redirect="/{locale}/checkout/{draft}/thank-you" data-storefront-refresh-on-error
                      data-storefront-load="/checkout/{draft}">
                    <div class="summary-line"><span>{{ trans('cart::checkout.line_items_label') }}</span><span class="val" data-field="price.subtotal_gross_amount"></span></div>
                    <div class="summary-line"><span>{{ trans('cart::checkout.delivery_label') }}</span><span class="val" data-field="price.delivery.selected.gross_amount"></span></div>
                    <div class="summary-line"><span>{{ trans('cart::checkout.discount_label') }}</span><span class="val" data-field="price.discount_amount"></span></div>
                    <div class="summary-total"><span>{{ trans('cart::checkout.total_label') }}</span><span class="val" data-field="price.grand_total_gross_amount"></span></div>

                    <p data-storefront-message role="alert" class="banner banner-error" style="margin-top: 18px;"></p>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 18px;">{{ trans('cart::checkout.place_order_button') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
