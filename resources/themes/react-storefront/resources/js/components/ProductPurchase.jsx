// The one interactive island on the product page. optionSignature joins axes with "," to match
// ProductVariantOfferData and runtime.js's matchesShowIf().

import { useMemo, useRef, useState } from 'react';
import { currentLocale, t } from '../api';

export default function ProductPurchase({ axes, offers, preselected, baseUrl, onAddToCart }) {
    const [selected, setSelected] = useState(() => {
        const initial = {};
        for (const axis of axes) {
            const preselectedValue = preselected?.[axis.key];
            if (preselectedValue) initial[axis.key] = preselectedValue;
            else if (axis.values[0]) initial[axis.key] = axis.values[0].key;
        }
        return initial;
    });
    const [qty, setQty] = useState(1);
    const [status, setStatus] = useState('idle'); // 'idle' | 'adding' | 'added' | 'error'

    const signature = useMemo(
        () => axes.map((axis) => `${axis.key}:${selected[axis.key]}`).join(','),
        [axes, selected],
    );

    // No axes (product without variants) => exactly one offer with an empty signature.
    const offer = useMemo(
        () => offers.find((o) => o.optionSignature === signature) ?? offers.find((o) => o.optionSignature === '') ?? null,
        [offers, signature],
    );

    const bridgeRootRef = useRef(null);

    // The picker itself is <button>/<div onClick>, not real radios, so the runtime.js
    // data-storefront-show-if engine (Specyfikacja tab, plugin tabs) has nothing to read.
    // These hidden, real radios mirror the picked values and fire a genuine "change" event,
    // which is all bindShowIf() actually listens for — no changes needed to runtime.js itself.
    function syncShowIfBridge(nextSelected) {
        const root = bridgeRootRef.current;
        if (!root) return;
        for (const [axisKey, valueKey] of Object.entries(nextSelected)) {
            const input = root.querySelector(`input[name="${CSS.escape(axisKey)}"][value="${CSS.escape(valueKey)}"]`);
            if (input && !input.checked) {
                input.checked = true;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    // Mirrors CatalogController::canonicalVariantSegments() — axes are already in
    // canonical (product_options.position) order, so no sorting is needed here.
    // replaceState (not pushState): reclicking swatches shouldn't fill up the
    // browser's Back history with one entry per combination.
    function syncVariantUrl(nextSelected) {
        if (!baseUrl) return;
        const segments = [];
        for (const axis of axes) {
            const value = axis.values.find((v) => v.key === nextSelected[axis.key]);
            if (!value) return;
            segments.push(axis.slug, value.slug);
        }
        const path = segments.length > 0 ? `${baseUrl}/${segments.join('/')}` : baseUrl;
        window.history.replaceState(null, '', path);
    }

    function pick(axisKey, valueKey) {
        setSelected((prev) => {
            const next = { ...prev, [axisKey]: valueKey };
            syncShowIfBridge(next);
            syncVariantUrl(next);
            return next;
        });
        setQty(1);
        setStatus('idle');
    }

    async function handleAddToCart() {
        if (!offer || !offer.available) return;
        setStatus('adding');
        try {
            await onAddToCart(offer.variantId, qty);
            setStatus('added');
        } catch {
            setStatus('error');
        }
    }

    return (
        <div className="product-purchase" data-testid="product-purchase">
            <div ref={bridgeRootRef} aria-hidden="true" style={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden', opacity: 0 }}>
                {axes.map((axis) =>
                    axis.values.map((value) => (
                        <input
                            key={`${axis.key}:${value.key}`}
                            type="radio"
                            name={axis.key}
                            value={value.key}
                            defaultChecked={selected[axis.key] === value.key}
                            tabIndex={-1}
                        />
                    )),
                )}
            </div>

            {axes.map((axis) => (
                <div className="option-group" key={axis.key}>
                    <p className="option-group-label">
                        {axis.name}
                        {axis.key in selected ? `: ${axis.values.find((v) => v.key === selected[axis.key])?.name ?? ''}` : ''}
                    </p>
                    <div className="option-values">
                        {axis.values.map((value) =>
                            axis.isColor ? (
                                <button
                                    key={value.key}
                                    type="button"
                                    className={`opt-swatch${selected[axis.key] === value.key ? ' is-selected' : ''}`}
                                    style={{ background: value.colorHex ?? 'transparent' }}
                                    title={value.name}
                                    aria-label={value.name}
                                    aria-pressed={selected[axis.key] === value.key}
                                    onClick={() => pick(axis.key, value.key)}
                                />
                            ) : (
                                <button
                                    key={value.key}
                                    type="button"
                                    className={`opt-pill${selected[axis.key] === value.key ? ' is-selected' : ''}`}
                                    aria-pressed={selected[axis.key] === value.key}
                                    onClick={() => pick(axis.key, value.key)}
                                >
                                    {value.name}
                                </button>
                            ),
                        )}
                    </div>
                </div>
            ))}

            {!offer && axes.length > 0 && (
                <p className="variant-unavailable">{t('product.pick_available_combination')}</p>
            )}

            {offer && (
                <>
                    <p className="sku-line">SKU: {offer.sku}</p>

                    <div className="price-block">
                        {offer.promotionalActive && offer.promotionalUnitAmountFormatted != null ? (
                            <>
                                <span className="price-regular">{offer.regularUnitAmountFormatted}</span>
                                <span className="price-current price-promo">{offer.promotionalUnitAmountFormatted}</span>
                            </>
                        ) : offer.unitAmountFormatted != null ? (
                            <span className="price-current">{offer.unitAmountFormatted}</span>
                        ) : (
                            <span className="muted">{t('product.price_unavailable')}</span>
                        )}

                        {!offer.available && <span className="badge badge-danger">{t('product.unavailable')}</span>}
                    </div>

                    <div className="purchase-row">
                        <div className="stepper">
                            <button
                                type="button"
                                onClick={() => setQty((q) => Math.max(1, q - 1))}
                                disabled={!offer.available}
                                aria-label={t('cart.decrease_quantity')}
                            >
                                –
                            </button>
                            <input className="mono" type="text" inputMode="numeric" value={qty} readOnly aria-label={t('cart.quantity')} />
                            <button type="button" onClick={() => setQty((q) => q + 1)} disabled={!offer.available} aria-label={t('cart.increase_quantity')}>
                                +
                            </button>
                        </div>
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={handleAddToCart}
                            disabled={!offer.available || status === 'adding'}
                        >
                            {status === 'adding' ? t('product.adding') : t('product.add_to_cart')}
                        </button>
                    </div>

                    {status === 'added' && (
                        <p className="feedback" role="status">
                            {t('product.added_n_to_cart', undefined, { n: qty })} <a href={`/${currentLocale()}/cart`}>{t('cart.go_to_cart')} →</a>
                        </p>
                    )}
                    {status === 'error' && (
                        <p className="feedback feedback-error" role="alert">
                            {t('product.add_to_cart_error')}
                        </p>
                    )}
                </>
            )}
        </div>
    );
}
