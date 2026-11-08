<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptCatalog;
use Litecommerce\Modules\Storefront\Contracts\OrderConfirmationPageData;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;

final class GuestOrderController extends Controller
{
    public function show(string $locale, string $token, GuestOrderAccess $access, PaymentAttemptCatalog $payments, DigitalAccessCatalog $digital, ThemeRenderer $renderer): View
    {
        $detail = $access->resolve($token);
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
