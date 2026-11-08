<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentExpiryReconciler
{
    public function reconcileDue(int $limit = 100): int;
}
