<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Litecommerce\Modules\Cart\Infrastructure\Models\CartCoupon;

final class DetachCouponCode
{
    public function execute(int $cartId, string $code): void
    {
        CartCoupon::query()->where('cart_id', $cartId)->where('code', strtoupper(trim($code)))->delete();
    }
}
