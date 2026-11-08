// Self-mounts into whichever root div the current page renders; a no-op elsewhere.

import { createRoot } from 'react-dom/client';
import ProductPurchase from './components/ProductPurchase';
import CartCheckout from './components/CartCheckout';
import { apiFetch } from './api';

function updateCartBadge(count) {
    document.querySelectorAll('[data-storefront="cart-count"]').forEach((el) => {
        el.textContent = String(count);
    });
}

// Guards against mounting the same root twice, which would race two instances' bootstrap effects.
function mountOnce(root, element) {
    if (root.dataset.mounted) {
        return;
    }
    root.dataset.mounted = 'true';
    createRoot(root).render(element);
}

const purchaseRoot = document.getElementById('product-purchase-root');
if (purchaseRoot) {
    const axes = JSON.parse(purchaseRoot.dataset.axes || '[]');
    const offers = JSON.parse(purchaseRoot.dataset.offers || '[]');
    const currencyCode = purchaseRoot.dataset.currency || 'PLN';
    const currencyDecimalPlaces = Number.parseInt(purchaseRoot.dataset.currencyDecimals || '2', 10);
    const preselected = JSON.parse(purchaseRoot.dataset.preselected || '{}');
    const baseUrl = purchaseRoot.dataset.baseUrl || '';

    mountOnce(
        purchaseRoot,
        <ProductPurchase
            axes={axes}
            offers={offers}
            currencyCode={currencyCode}
            currencyDecimalPlaces={currencyDecimalPlaces}
            preselected={preselected}
            baseUrl={baseUrl}
            onAddToCart={async (variantId, quantity) => {
                await apiFetch('/cart/items', { method: 'POST', body: { variant_id: variantId, quantity } });
                const cart = await apiFetch('/cart');
                // quantity is a decimal string, not a number — Number() avoids string concatenation.
                updateCartBadge(cart.lines?.reduce((n, l) => n + Number(l.quantity), 0) ?? 0);
            }}
        />,
    );
}

const cartRoot = document.getElementById('cart-checkout-root');
if (cartRoot) {
    mountOnce(cartRoot, <CartCheckout step={cartRoot.dataset.step} apiFetch={apiFetch} onCartCountChange={updateCartBadge} />);
}
