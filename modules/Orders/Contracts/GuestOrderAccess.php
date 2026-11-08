<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

interface GuestOrderAccess
{
    public function issue(string $orderId): string;

    public function resolve(string $token): ?OrderDetailData;

    public function revokeForOrder(string $orderId): void;
}
