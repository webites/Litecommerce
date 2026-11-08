<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Orders\Contracts\Events\OrderPersonalDataAnonymized;
use Litecommerce\Modules\Orders\Infrastructure\Models\GuestOrderAccessToken;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderNote;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderOutboxEvent;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderPartySnapshot;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderStatusHistory;

/**
 * Removes the customer's personal data from an order once its retention
 * period ended. Amounts, lines, taxes and statuses stay: they are the
 * accounting record.
 */
final class AnonymizeExpiredOrders
{
    public function anonymizeBatch(\DateTimeImmutable $createdBefore, int $batchSize): int
    {
        $ids = Order::query()->whereNull('anonymized_at')->where('created_at', '<', $createdBefore)
            ->orderBy('created_at')->limit($batchSize)->pluck('id')->all();

        foreach ($ids as $id) {
            $this->anonymize((string) $id);
        }

        return count($ids);
    }

    public function anonymize(string $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($order === null || $order->anonymized_at !== null) {
                return false;
            }

            $now = now();
            $placeholder = 'anonymized-'.Str::substr($order->id, 0, 8).'@anonymized.invalid';
            $party = OrderPartySnapshot::query()->where('order_id', $order->id)->first();

            $order->update(['email' => $placeholder, 'customer_account_id' => null, 'anonymized_at' => $now, 'version' => $order->version + 1]);
            $party?->update([
                'email' => $placeholder, 'company_name' => null, 'tax_identifier' => null, 'registration_number' => null, 'phone' => null,
                'billing_address' => $this->keepCountry($party->billing_address) ?? [],
                'shipping_address' => $this->keepCountry($party->shipping_address),
            ]);
            OrderNote::query()->where('order_id', $order->id)->update(['body' => '[usunięto]']);
            OrderStatusHistory::query()->where('order_id', $order->id)->where('actor_type', 'staff')->update(['reason' => null]);
            GuestOrderAccessToken::query()->where('order_id', $order->id)->delete();
            OrderOutboxEvent::query()->create([
                'id' => (string) Str::uuid(), 'order_id' => $order->id, 'event_type' => 'OrderPersonalDataAnonymized',
                'aggregate_version' => $order->version, 'payload' => [], 'occurred_at' => $now, 'available_at' => $now,
            ]);

            $event = new OrderPersonalDataAnonymized($order->id, \DateTimeImmutable::createFromInterface($now));
            DB::afterCommit(static fn () => CommittedEvent::dispatch($event));

            return true;
        });
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>|null
     */
    private function keepCountry(?array $address): ?array
    {
        return $address === null ? null : array_filter(['country_code' => $address['country_code'] ?? null]);
    }
}
