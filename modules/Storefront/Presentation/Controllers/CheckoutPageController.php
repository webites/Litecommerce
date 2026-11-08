<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Litecommerce\Modules\Cart\Contracts\CartOwnershipResolver;
use Litecommerce\Modules\Cart\Contracts\CheckoutDraftCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderConfirmationCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderPlacer;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptCatalog;
use Litecommerce\Modules\Storefront\Contracts\OrderConfirmationPageData;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;

final class CheckoutPageController extends Controller
{
    /** Static shell, same reasoning as CartPageController — the theme's JS drives the checkout form through the existing JSON API. */
    public function show(Request $request, string $locale, ThemeRenderer $renderer): View
    {
        return $renderer->render('checkout.show', ['locale' => $locale]);
    }

    /**
     * Re-read on every visit (not just once, right after confirm()) so a
     * bookmarked/refreshed confirmation link keeps working — see "Gość
     * ogląda potwierdzenie zamówienia przez istniejące ciasteczko koszyka"
     * (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
     * Ownership is re-verified here (findConfirmedOwnedBy), not assumed
     * from the {draft} URL segment alone. $locale and $draft are both
     * declared (matching route segment order) because Laravel fills a
     * controller method's non-class-typed parameters from the route's raw
     * parameter list positionally — declaring only $draft would have
     * silently received the {locale} segment's value instead.
     */
    public function confirmation(
        Request $request,
        string $locale,
        string $draft,
        CartOwnershipResolver $ownership,
        CheckoutDraftCatalog $drafts,
        OrderPlacer $orderPlacer,
        OrderConfirmationCatalog $orders,
        PaymentAttemptCatalog $payments,
        DigitalAccessCatalog $digital,
        ThemeRenderer $renderer,
    ): View {
        $ownedCartIds = $ownership->resolveOwningCartIds($request);

        abort_if($drafts->findConfirmedOwnedBy($draft, $ownedCartIds) === null, 404);

        $placed = $orderPlacer->findBySource('checkout_draft', $draft);
        abort_if($placed === null, 404);

        $order = $orders->find($placed->id);
        abort_if($order === null, 404);

        $attempts = $payments->forOrder($order->id);
        $latestPayment = $attempts === [] ? null : $attempts[array_key_last($attempts)];

        $grants = array_values(array_filter($digital->findForOrder($order->id), static fn ($grant): bool => $grant->isUsable()));

        return $renderer->render('checkout.confirmation', [
            'page' => new OrderConfirmationPageData($order, $latestPayment, $grants),
            'locale' => $locale,
        ]);
    }
}
