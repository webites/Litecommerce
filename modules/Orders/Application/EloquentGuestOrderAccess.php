<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Orders\Contracts\OrderDetailData;
use Litecommerce\Modules\Orders\Infrastructure\Models\GuestOrderAccessToken;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;

final class EloquentGuestOrderAccess implements GuestOrderAccess
{
    public function __construct(private readonly OrderProjector $projector) {}

    public function issue(string $orderId): string
    {
        Order::query()->whereNull('customer_account_id')->findOrFail($orderId);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        GuestOrderAccessToken::query()->create([
            'order_id' => $orderId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays((int) config('orders.guest_token_ttl_days', 365)),
        ]);

        return $token;
    }

    public function resolve(string $token): ?OrderDetailData
    {
        $access = GuestOrderAccessToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if ($access === null) {
            return null;
        }
        $order = Order::query()->whereNull('customer_account_id')
            ->with(['party', 'delivery', 'lines'])
            ->find($access->order_id);

        return $order === null ? null : $this->projector->detail($order);
    }

    public function revokeForOrder(string $orderId): void
    {
        GuestOrderAccessToken::query()->where('order_id', $orderId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
