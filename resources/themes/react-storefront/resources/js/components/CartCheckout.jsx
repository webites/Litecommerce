// One island for both /koszyk and /checkout, same component, different `step`.
// PUT /checkout/{draft} never returns "price" — only GET does — so every PUT here refreshes it after.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { currentLocale, t } from '../api';
import CountrySelect from './CountrySelect';

const fmt = (n) =>
    new Intl.NumberFormat(currentLocale() === 'pl' ? 'pl-PL' : 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n)) + ' zł';

function PlaceholderPhotoIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <polyline points="21 15 16 10 5 21" />
        </svg>
    );
}

const emptyAddress = () => ({
    recipient_name: '', country_code: '', postal_code: '', city: '', address_line_1: '', address_line_2: '', phone: '',
});

// checkoutSuccessUrl defaults to "/checkout/{draft}/thank-you" when omitted.
export default function CartCheckout({ step, apiFetch, onCartCountChange, checkoutSuccessUrl }) {
    const [cart, setCart] = useState(null);
    const [draftId, setDraftId] = useState(null);
    const [checkoutPrice, setCheckoutPrice] = useState(null);
    const [paymentMethods, setPaymentMethods] = useState(null);
    const [buyerType, setBuyerType] = useState('person');
    const [shipsToDifferent, setShipsToDifferent] = useState(false);
    const [addressForm, setAddressForm] = useState({
        email: '', phone: '', company_name: '', tax_identifier: '', notes: '', marketing_consent: false,
        billing_address: emptyAddress(),
        shipping_address: emptyAddress(),
    });
    const [deliveryMethodId, setDeliveryMethodId] = useState(null);
    const [pickupPointId, setPickupPointId] = useState(null);
    const [paymentMethodId, setPaymentMethodId] = useState(null);
    const [confirmError, setConfirmError] = useState(null);
    const [saveError, setSaveError] = useState(null);
    const [fieldErrors, setFieldErrors] = useState({});
    const [submitted, setSubmitted] = useState(false);
    const [busy, setBusy] = useState(false);

    // Validation errors only surface after the user submits the form at least once.
    function applyApiError(e, fallbackMessage) {
        const errors = e?.status === 422 ? e.body?.errors : null;
        setFieldErrors(errors ?? {});
        setSaveError(errors ? null : (e && e.body && e.body.message) || fallbackMessage);
    }

    function fieldError(path) {
        if (!submitted) return undefined;
        const value = fieldErrors[path];
        return Array.isArray(value) ? value[0] : value;
    }

    // Read from a ref so a delivery pick resubmits current form values without re-running on every keystroke.
    const formStateRef = useRef();
    formStateRef.current = { buyerType, addressForm, shipsToDifferent };

    const reloadCart = useCallback(async () => {
        const data = await apiFetch('/cart');
        setCart(data);
        // quantity is a decimal string, not a number — Number() avoids string concatenation.
        onCartCountChange?.(data.lines?.reduce((n, l) => n + Number(l.quantity), 0) ?? 0);
    }, [apiFetch, onCartCountChange]);

    useEffect(() => {
        reloadCart();
    }, [reloadCart]);

    // Only GET /checkout/{draft} returns "price"; PUT doesn't, so every successful PUT calls this after.
    const refreshPrice = useCallback(async (id) => {
        const { price } = await apiFetch(`/checkout/${id}`);
        setCheckoutPrice(price);
        return price;
    }, [apiFetch]);

    // ---- checkout draft bootstrap (step="checkout" only) ----
    useEffect(() => {
        if (step !== 'checkout') return;
        (async () => {
            const { id } = await apiFetch('/checkout', { method: 'POST' });
            setDraftId(id);
            const price = await refreshPrice(id);
            const options = price.delivery?.options ?? [];
            const preselected = options.find((o) => o.preselect) ?? options[0];
            if (preselected) setDeliveryMethodId(preselected.method_id);
        })();
    }, [step, apiFetch, refreshPrice]);

    // Recalculate on delivery method / pickup point change, resending the whole current form.
    useEffect(() => {
        if (step !== 'checkout' || !draftId || !deliveryMethodId) return;
        (async () => {
            const { buyerType: currentBuyerType, addressForm: currentAddress, shipsToDifferent: currentShipsToDifferent } = formStateRef.current;
            try {
                await apiFetch(`/checkout/${draftId}`, {
                    method: 'PUT',
                    body: {
                        buyer_type: currentBuyerType,
                        ...currentAddress,
                        ships_to_different_address: currentShipsToDifferent,
                        delivery_method_id: deliveryMethodId,
                        delivery_pickup_point_id: pickupPointId,
                    },
                });
                await refreshPrice(draftId);
                setSaveError(null);
                setFieldErrors({});
            } catch (e) {
                applyApiError(e, t('checkout.delivery_save_error'));
            }
        })();
    }, [step, draftId, deliveryMethodId, pickupPointId, apiFetch, refreshPrice]);

    // ---- payment methods depend on the current price/discount (e.g. a coupon covering the whole order) ----
    useEffect(() => {
        if (step !== 'checkout' || !draftId || !checkoutPrice) return;
        (async () => {
            const { methods } = await apiFetch(`/checkout/${draftId}/payment-methods`);
            setPaymentMethods(methods);
            const preselected = methods.find((m) => m.preselect) ?? methods[0];
            setPaymentMethodId(preselected?.id ?? null);
        })();
    }, [step, draftId, checkoutPrice, apiFetch]);

    // ---- cart actions ----
    async function changeQuantity(variantId, quantity) {
        setBusy(true);
        try {
            await apiFetch(`/cart/items/${variantId}`, { method: 'PUT', body: { quantity } });
            await reloadCart();
        } finally {
            setBusy(false);
        }
    }

    async function removeLine(variantId) {
        setBusy(true);
        try {
            await apiFetch(`/cart/items/${variantId}`, { method: 'DELETE' });
            await reloadCart();
        } finally {
            setBusy(false);
        }
    }

    async function applyCoupon(code, pin) {
        setBusy(true);
        try {
            await apiFetch('/cart/coupons', { method: 'POST', body: { code, pin: pin || undefined } });
            await reloadCart();
        } finally {
            setBusy(false);
        }
    }

    async function removeCoupon(code) {
        setBusy(true);
        try {
            await apiFetch(`/cart/coupons/${code}`, { method: 'DELETE' });
            await reloadCart();
        } finally {
            setBusy(false);
        }
    }

    // Plain button, not a <form onSubmit>, to avoid a native submission that reloads and loses React state.
    async function saveAddress() {
        setSubmitted(true);
        setSaveError(null);
        setFieldErrors({});
        setBusy(true);
        try {
            await apiFetch(`/checkout/${draftId}`, {
                method: 'PUT',
                body: {
                    buyer_type: buyerType,
                    ...addressForm,
                    ships_to_different_address: shipsToDifferent,
                    delivery_method_id: deliveryMethodId,
                    delivery_pickup_point_id: pickupPointId,
                },
            });
            await refreshPrice(draftId);
        } catch (e) {
            applyApiError(e, t('checkout.generic_save_error'));
        } finally {
            setBusy(false);
        }
    }

    function setField(path, value) {
        setAddressForm((prev) => {
            const next = { ...prev };
            const keys = path.split('.');
            let node = next;
            for (let i = 0; i < keys.length - 1; i++) {
                node[keys[i]] = { ...node[keys[i]] };
                node = node[keys[i]];
            }
            node[keys[keys.length - 1]] = value;
            return next;
        });
    }

    // ---- place the order ----
    async function confirmOrder() {
        setConfirmError(null);
        setBusy(true);
        try {
            await apiFetch(`/checkout/${draftId}/confirm`, {
                method: 'POST',
                body: { payment_method_id: paymentMethodId, price_hash: checkoutPrice?.hash },
            });
            window.location.href = checkoutSuccessUrl ?? `/${currentLocale()}/checkout/${draftId}/thank-you`;
        } catch (e) {
            if (e?.code === 'price_changed') {
                setConfirmError(t('checkout.price_changed'));
                await refreshPrice(draftId);
            } else {
                setConfirmError((e && e.body && e.body.message) || t('checkout.generic_confirm_error'));
            }
        } finally {
            setBusy(false);
        }
    }

    // One row per delivery method, except a "pickup" method with points becomes one row per point.
    const deliveryRows = useMemo(() => {
        const options = checkoutPrice?.delivery?.options ?? [];
        const rows = [];
        options.forEach((option) => {
            if (option.kind === 'pickup' && option.pickup_points?.length > 0) {
                option.pickup_points.forEach((point) => {
                    rows.push({ ...option, pickup_point_id: point.id, label: `${option.name} — ${point.name}` });
                });
            } else {
                rows.push({ ...option, pickup_point_id: '', label: option.name });
            }
        });
        return rows;
    }, [checkoutPrice]);

    if (step === 'cart') {
        if (!cart) return <CartSkeleton />;
        return (
            <CartView
                cart={cart}
                busy={busy}
                onChangeQuantity={changeQuantity}
                onRemoveLine={removeLine}
                onApplyCoupon={applyCoupon}
                onRemoveCoupon={removeCoupon}
            />
        );
    }

    if (!checkoutPrice) return <CartSkeleton />;

    return (
        <CheckoutView
            cart={cart}
            price={checkoutPrice}
            deliveryRows={deliveryRows}
            paymentMethods={paymentMethods ?? []}
            buyerType={buyerType}
            onBuyerTypeChange={setBuyerType}
            addressForm={addressForm}
            onFieldChange={setField}
            fieldError={fieldError}
            shipsToDifferent={shipsToDifferent}
            onShipsToDifferentChange={setShipsToDifferent}
            deliveryMethodId={deliveryMethodId}
            pickupPointId={pickupPointId}
            onDeliveryRowChange={(row) => {
                setDeliveryMethodId(row.method_id);
                setPickupPointId(row.pickup_point_id || null);
            }}
            paymentMethodId={paymentMethodId}
            onPaymentMethodChange={setPaymentMethodId}
            saveError={submitted ? saveError : null}
            onSaveAddress={saveAddress}
            confirmError={confirmError}
            busy={busy}
            onConfirm={confirmOrder}
        />
    );
}

function CartSkeleton() {
    return <div className="card" style={{ padding: 24, color: 'var(--ink-muted)' }}>{t('cart.loading')}</div>;
}

function CartView({ cart, busy, onChangeQuantity, onRemoveLine, onApplyCoupon, onRemoveCoupon }) {
    const [code, setCode] = useState('');
    const [pin, setPin] = useState('');

    if (cart.lines.length === 0) {
        return (
            <div className="empty-state">
                <p>{t('cart.empty_title')}</p>
                <a className="btn btn-primary" href={`/${currentLocale()}`}>{t('cart.browse_products')}</a>
            </div>
        );
    }

    return (
        <div className="layout-2col">
            <div>
                <div className="card">
                    <table className="cart-table">
                        <thead>
                            <tr><th>{t('cart.table_product')}</th><th>{t('cart.table_quantity')}</th><th>{t('cart.table_price')}</th><th>{t('cart.table_total')}</th><th /></tr>
                        </thead>
                        <tbody>
                            {cart.lines.map((line) => (
                                <tr key={line.variant_id}>
                                    <td>
                                        <div className="line-product">
                                            <div className="line-thumb" aria-hidden="true">
                                                {line.image_url ? <img src={line.image_url} alt="" /> : <PlaceholderPhotoIcon />}
                                            </div>
                                            <div>
                                                <div className="line-name">{line.display_name}</div>
                                                {!line.available && (
                                                    <div className="unavailable">{t('cart.unavailable_prefix')} {line.unavailable_reason}</div>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div className="stepper-sm">
                                            <button type="button" disabled={busy} onClick={() => onChangeQuantity(line.variant_id, Number(line.quantity) - 1)}>–</button>
                                            <input className="mono" type="text" value={line.quantity} readOnly />
                                            <button type="button" disabled={busy} onClick={() => onChangeQuantity(line.variant_id, Number(line.quantity) + 1)}>+</button>
                                        </div>
                                    </td>
                                    <td className="mono">{fmt(line.unit_amount)}</td>
                                    <td className="mono">{fmt(line.line_gross_amount)}</td>
                                    <td><button type="button" className="remove-btn" disabled={busy} onClick={() => onRemoveLine(line.variant_id)}>{t('cart.remove')}</button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <form
                        className="coupon-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            onApplyCoupon(code, pin);
                            setCode('');
                            setPin('');
                        }}
                    >
                        <div className="field">
                            <label htmlFor="coupon-code">{t('cart.coupon_code_label')}</label>
                            <input id="coupon-code" value={code} onChange={(e) => setCode(e.target.value)} />
                        </div>
                        <div className="field">
                            <label htmlFor="coupon-pin">{t('cart.coupon_pin_label')}</label>
                            <input id="coupon-pin" value={pin} onChange={(e) => setPin(e.target.value)} />
                        </div>
                        <button type="submit" className="btn btn-secondary btn-sm" disabled={busy || !code}>{t('cart.add_coupon')}</button>
                    </form>

                    {cart.coupons?.length > 0 && (
                        <ul className="coupon-list">
                            {cart.coupons.map((c) => (
                                <li key={c.code}>
                                    <span><span className="code">{c.code}</span> ({c.status}) — {fmt(c.allocated_amount)}</span>
                                    <button type="button" className="remove-btn" onClick={() => onRemoveCoupon(c.code)}>{t('cart.remove')}</button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <div className="card summary">
                <div className="summary-line"><span>{t('cart.subtotal')}</span><span className="val">{fmt(cart.subtotal_gross_amount)}</span></div>
                <div className="summary-line"><span>{t('cart.discount')}</span><span className="val">-{fmt(cart.discount_amount ?? 0)}</span></div>
                <div className="summary-total"><span>{t('cart.total')}</span><span className="val">{fmt(cart.total_gross_amount)}</span></div>
                <a className="btn btn-primary btn-block" href={`/${currentLocale()}/checkout`} style={{ marginTop: 18 }}>{t('cart.go_to_checkout')}</a>
            </div>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <div className={`field${error ? ' has-error' : ''}`}>
            <label>{label}</label>
            {children}
            {error && <span className="field-error">{error}</span>}
        </div>
    );
}

function CheckoutView({
    cart, price, deliveryRows, paymentMethods,
    buyerType, onBuyerTypeChange,
    addressForm, onFieldChange, fieldError,
    shipsToDifferent, onShipsToDifferentChange,
    deliveryMethodId, pickupPointId, onDeliveryRowChange,
    paymentMethodId, onPaymentMethodChange,
    saveError, onSaveAddress,
    confirmError, busy, onConfirm,
}) {
    const deliveryAmount = price.delivery?.selected?.gross_amount;

    return (
        <div className="layout-2col">
            <div className="card form-card">
                <h3 className="section-h">{t('checkout.section_title')}</h3>
                <p className="section-sub">{t('checkout.section_sub')}</p>

                <div className="segmented" role="group" aria-label={t('checkout.buyer_type_group_label')}>
                    <button type="button" className={buyerType === 'person' ? 'is-active' : ''} onClick={() => onBuyerTypeChange('person')}>
                        {t('checkout.buyer_type_person')}
                    </button>
                    <button type="button" className={buyerType === 'company' ? 'is-active' : ''} onClick={() => onBuyerTypeChange('company')}>
                        {t('checkout.buyer_type_company')}
                    </button>
                </div>

                {buyerType === 'company' && (
                    <div className="field-row">
                        <Field label={t('checkout.company_name_label')} error={fieldError('company_name')}>
                            <input value={addressForm.company_name} onChange={(e) => onFieldChange('company_name', e.target.value)} />
                        </Field>
                        <Field label={t('checkout.tax_id_label')} error={fieldError('tax_identifier')}>
                            <input value={addressForm.tax_identifier} onChange={(e) => onFieldChange('tax_identifier', e.target.value)} />
                        </Field>
                    </div>
                )}

                <div className="field-row">
                    <Field label={t('checkout.email_label')} error={fieldError('email')}>
                        <input type="email" required value={addressForm.email} onChange={(e) => onFieldChange('email', e.target.value)} />
                    </Field>
                    <Field label={t('checkout.phone_label')} error={fieldError('phone')}>
                        <input value={addressForm.phone} onChange={(e) => onFieldChange('phone', e.target.value)} />
                    </Field>
                </div>

                <fieldset>
                    <legend>{t('checkout.billing_address_legend')}</legend>
                    <Field label={t('checkout.recipient_name_label')} error={fieldError('billing_address.recipient_name')}>
                        <input value={addressForm.billing_address.recipient_name} onChange={(e) => onFieldChange('billing_address.recipient_name', e.target.value)} />
                    </Field>
                    <div className="field-row">
                        <Field label={t('checkout.country_code_label')} error={fieldError('billing_address.country_code')}>
                            <CountrySelect value={addressForm.billing_address.country_code} onChange={(code) => onFieldChange('billing_address.country_code', code)} />
                        </Field>
                        <Field label={t('checkout.postal_code_label')} error={fieldError('billing_address.postal_code')}>
                            <input value={addressForm.billing_address.postal_code} onChange={(e) => onFieldChange('billing_address.postal_code', e.target.value)} />
                        </Field>
                    </div>
                    <Field label={t('checkout.city_label')} error={fieldError('billing_address.city')}>
                        <input value={addressForm.billing_address.city} onChange={(e) => onFieldChange('billing_address.city', e.target.value)} />
                    </Field>
                    <Field label={t('checkout.address_label')} error={fieldError('billing_address.address_line_1')}>
                        <input value={addressForm.billing_address.address_line_1} onChange={(e) => onFieldChange('billing_address.address_line_1', e.target.value)} />
                    </Field>
                    <Field label={t('checkout.address_line_2_label')} error={fieldError('billing_address.address_line_2')}>
                        <input value={addressForm.billing_address.address_line_2} onChange={(e) => onFieldChange('billing_address.address_line_2', e.target.value)} />
                    </Field>
                    <Field label={t('checkout.phone_label')} error={fieldError('billing_address.phone')}>
                        <input value={addressForm.billing_address.phone} onChange={(e) => onFieldChange('billing_address.phone', e.target.value)} />
                    </Field>
                </fieldset>

                <label className="check-row">
                    <input type="checkbox" checked={shipsToDifferent} onChange={(e) => onShipsToDifferentChange(e.target.checked)} />
                    {t('checkout.ships_to_different_address_label')}
                </label>

                {shipsToDifferent && (
                    <fieldset>
                        <legend>{t('checkout.shipping_address_legend')}</legend>
                        <Field label={t('checkout.recipient_name_label')} error={fieldError('shipping_address.recipient_name')}>
                            <input value={addressForm.shipping_address.recipient_name} onChange={(e) => onFieldChange('shipping_address.recipient_name', e.target.value)} />
                        </Field>
                        <div className="field-row">
                            <Field label={t('checkout.country_code_label')} error={fieldError('shipping_address.country_code')}>
                                <CountrySelect value={addressForm.shipping_address.country_code} onChange={(code) => onFieldChange('shipping_address.country_code', code)} />
                            </Field>
                            <Field label={t('checkout.postal_code_label')} error={fieldError('shipping_address.postal_code')}>
                                <input value={addressForm.shipping_address.postal_code} onChange={(e) => onFieldChange('shipping_address.postal_code', e.target.value)} />
                            </Field>
                        </div>
                        <Field label={t('checkout.city_label')} error={fieldError('shipping_address.city')}>
                            <input value={addressForm.shipping_address.city} onChange={(e) => onFieldChange('shipping_address.city', e.target.value)} />
                        </Field>
                        <Field label={t('checkout.address_label')} error={fieldError('shipping_address.address_line_1')}>
                            <input value={addressForm.shipping_address.address_line_1} onChange={(e) => onFieldChange('shipping_address.address_line_1', e.target.value)} />
                        </Field>
                        <Field label={t('checkout.address_line_2_label')} error={fieldError('shipping_address.address_line_2')}>
                            <input value={addressForm.shipping_address.address_line_2} onChange={(e) => onFieldChange('shipping_address.address_line_2', e.target.value)} />
                        </Field>
                        <Field label={t('checkout.phone_label')} error={fieldError('shipping_address.phone')}>
                            <input value={addressForm.shipping_address.phone} onChange={(e) => onFieldChange('shipping_address.phone', e.target.value)} />
                        </Field>
                    </fieldset>
                )}

                <Field label={t('checkout.notes_label')} error={fieldError('notes')}>
                    <textarea value={addressForm.notes} onChange={(e) => onFieldChange('notes', e.target.value)} />
                </Field>

                <label className="check-row">
                    <input type="checkbox" checked={addressForm.marketing_consent} onChange={(e) => onFieldChange('marketing_consent', e.target.checked)} />
                    {t('marketing.statement')}
                </label>

                {saveError && <p className="feedback feedback-error" role="alert">{saveError}</p>}
                <button type="button" className="btn btn-primary" style={{ marginTop: 20 }} disabled={busy} onClick={onSaveAddress}>{t('checkout.recalculate_button')}</button>
            </div>

            <div>
                <div className="card form-card" style={{ marginBottom: 20 }}>
                    <h3 className="section-h">{t('checkout.delivery_title')}</h3>
                    <p className="section-sub">{t('checkout.delivery_sub')}</p>
                    {deliveryRows.map((row) => {
                        const isSelected = deliveryMethodId === row.method_id && (pickupPointId || '') === (row.pickup_point_id || '');
                        return (
                            <div
                                key={`${row.method_id}:${row.pickup_point_id}`}
                                className={`radio-card${isSelected ? ' is-selected' : ''}`}
                                onClick={() => onDeliveryRowChange(row)}
                                role="radio"
                                aria-checked={isSelected}
                                tabIndex={0}
                            >
                                <div className="left"><span className="radio-dot" /><span>{row.label}</span></div>
                                <span className="price mono">{Number(row.gross_amount) === 0 ? t('checkout.free') : fmt(row.gross_amount)}</span>
                            </div>
                        );
                    })}
                </div>

                <div className="card form-card" style={{ marginBottom: 20 }}>
                    <h3 className="section-h">{t('checkout.payment_title')}</h3>
                    {paymentMethods.length === 0 && (
                        <p className="section-sub" style={{ marginTop: 10 }}>{t('checkout.no_payment_methods')}</p>
                    )}
                    {paymentMethods.map((m) => (
                        <div
                            key={m.id}
                            className={`radio-card${paymentMethodId === m.id ? ' is-selected' : ''}`}
                            onClick={() => onPaymentMethodChange(m.id)}
                            role="radio"
                            aria-checked={paymentMethodId === m.id}
                            tabIndex={0}
                        >
                            <div className="left"><span className="radio-dot" /><span>{m.name}</span></div>
                        </div>
                    ))}
                </div>

                <div className="card summary">
                    <div className="summary-line"><span>{t('checkout.line_items_label')} ({cart?.lines?.length ?? 0})</span><span className="val">{fmt(price.subtotal_gross_amount)}</span></div>
                    <div className="summary-line"><span>{t('checkout.delivery_label')}</span><span className="val">{deliveryAmount == null ? '—' : Number(deliveryAmount) === 0 ? t('checkout.free') : fmt(deliveryAmount)}</span></div>
                    <div className="summary-line"><span>{t('checkout.discount_label')}</span><span className="val">-{fmt(price.discount_amount ?? 0)}</span></div>
                    <div className="summary-total"><span>{t('checkout.total_label')}</span><span className="val">{fmt(price.grand_total_gross_amount)}</span></div>

                    {confirmError && (
                        <div className="banner banner-error">
                            <span>{confirmError}</span>
                        </div>
                    )}

                    <button
                        type="button"
                        className="btn btn-primary btn-block"
                        style={{ marginTop: 18 }}
                        disabled={busy || !paymentMethodId}
                        onClick={onConfirm}
                    >
                        {t('checkout.place_order_button')}
                    </button>
                </div>
            </div>
        </div>
    );
}
