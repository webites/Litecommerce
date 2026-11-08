<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Orders\Contracts\CustomerOrderCatalog;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptCatalog;
use Litecommerce\Modules\Storefront\Contracts\CustomerOrderListPageData;
use Litecommerce\Modules\Storefront\Contracts\OrderConfirmationPageData;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;

/** Gated by 'auth:customer' at the route level — CustomerOrderCatalog additionally self-filters by customer_account_id (see its docblock), so a route misconfiguration alone could never leak another customer's order. */
final class CustomerOrderController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, string $locale, CustomerOrderCatalog $orders, ThemeRenderer $renderer): View
    {
        $customerId = (int) Auth::guard('customer')->id();
        $page = max(1, $request->integer('page', 1));

        $result = $orders->paginateForCustomer($customerId, self::PER_PAGE, $page);

        return $renderer->render('account.orders.index', [
            'page' => new CustomerOrderListPageData($result['items'], $result['total'], $page, self::PER_PAGE),
            'locale' => $locale,
        ]);
    }

    /** $locale and $order are both declared (route segment order) — see CheckoutPageController::confirmation()'s docblock for why. */
    public function show(Request $request, string $locale, string $order, CustomerOrderCatalog $orders, PaymentAttemptCatalog $payments, DigitalAccessCatalog $digital, ThemeRenderer $renderer): View
    {
        $customerId = (int) Auth::guard('customer')->id();
        $detail = $orders->findForCustomer($order, $customerId);

        abort_if($detail === null, 404);

        $attempts = $payments->forOrder($detail->id);
        $latestPayment = $attempts === [] ? null : $attempts[array_key_last($attempts)];
        $grants = array_values(array_filter($digital->findForOrder($detail->id), static fn ($grant): bool => $grant->isUsable()));

        return $renderer->render('account.orders.show', [
            'page' => new OrderConfirmationPageData($detail, $latestPayment, $grants),
            'locale' => $locale,
        ]);
    }
}
