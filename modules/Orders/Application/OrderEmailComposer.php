<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Illuminate\Support\Facades\Lang;
use Litecommerce\Modules\Digital\Contracts\DigitalGrantData;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Orders\Contracts\OrderStatusRegistry;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderDelivery;
use Litecommerce\Modules\Store\Contracts\AddressFormatter;
use Litecommerce\Modules\Store\Contracts\DateTimeFormatter;
use Litecommerce\Modules\Store\Contracts\MoneyFormatter;
use Litecommerce\Modules\Store\Contracts\QuantityFormatter;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

final class OrderEmailComposer
{
    public function __construct(
        private readonly TransactionalEmailQueue $queue,
        private readonly GuestOrderAccess $guestAccess,
        private readonly StoreSettingsReader $store,
        private readonly StoreSettingsStore $settings,
        private readonly OrderStatusRegistry $statuses,
        private readonly MoneyFormatter $money,
        private readonly DateTimeFormatter $dates,
        private readonly QuantityFormatter $quantities,
        private readonly AddressFormatter $addresses,
    ) {}

    public function orderPlaced(Order $order): void
    {
        $order->loadMissing(['party', 'lines']);
        $this->queue->enqueue($this->envelope($order, 'core.order_placed', [
            ...$this->base($order),
            'lines' => $order->lines->map(fn ($line): array => [
                'name' => $line->display_name,
                'quantity' => $this->quantities->format((string) $line->quantity, $order->locale),
            ])->values()->all(),
        ], "order:{$order->id}:placed"));
    }

    public function paymentConfirmed(Order $order, string $attemptId): void
    {
        $this->queue->enqueue($this->envelope($order, 'core.payment_confirmed', $this->base($order), "order:{$order->id}:payment:{$attemptId}"));
    }

    public function statusChanged(Order $order, string $oldStatus, string $newStatus, ?string $reason): void
    {
        $enabled = array_values(array_filter((array) $this->settings->get('email.order_statuses', []), 'is_string'));
        if (! in_array($newStatus, $enabled, true)) {
            return;
        }
        $definition = collect($this->statuses->all())->keyBy('key');
        $oldDefinition = $definition->get($oldStatus);
        $newDefinition = $definition->get($newStatus);
        $this->queue->enqueue($this->envelope($order, 'core.order_status_changed', [
            ...$this->base($order),
            'old_status' => Lang::get($oldDefinition === null ? $oldStatus : $oldDefinition->labelKey, [], $order->locale),
            'new_status' => Lang::get($newDefinition === null ? $newStatus : $newDefinition->labelKey, [], $order->locale),
            'reason' => $reason ?? '',
        ], "order:{$order->id}:status:{$order->version}"));
    }

    public function readyForPickup(Order $order, OrderDelivery $delivery): void
    {
        $this->queue->enqueue($this->envelope($order, 'core.order_ready_for_pickup', $this->pickupVariables($order, $delivery), "order:{$order->id}:ready_for_pickup"));
    }

    public function pickupReminder(Order $order, OrderDelivery $delivery, bool $lapses): void
    {
        $this->queue->enqueue($this->envelope($order, 'core.order_pickup_reminder', [
            ...$this->pickupVariables($order, $delivery),
            'lapses' => $lapses ? '1' : '',
        ], "order:{$order->id}:pickup_reminder"));
    }

    public function pickupExpired(Order $order, OrderDelivery $delivery): void
    {
        $this->queue->enqueue($this->envelope($order, 'core.order_pickup_expired', [
            'store_name' => $this->store->current()->storeName,
            'order_id' => $order->id,
            'order_number' => $order->public_number,
            'order_url' => $this->orderUrl($order),
            'pickup_deadline' => $this->deadline($order, $delivery),
        ], "order:{$order->id}:pickup_expired"));
    }

    /** @return array<string, string> */
    private function pickupVariables(Order $order, OrderDelivery $delivery): array
    {
        $point = $delivery->pickup_point_snapshot ?? [];

        return [
            'store_name' => $this->store->current()->storeName,
            'order_id' => $order->id,
            'order_number' => $order->public_number,
            'order_url' => $this->orderUrl($order),
            'pickup_name' => (string) ($point['name'] ?? $delivery->name ?? ''),
            'pickup_address' => $this->addresses->inline($point, $order->locale),
            'pickup_instructions' => (string) ($point['instructions'] ?? ''),
            'pickup_deadline' => $this->deadline($order, $delivery),
        ];
    }

    private function deadline(Order $order, OrderDelivery $delivery): string
    {
        return $delivery->pickup_deadline_at === null ? '' : $this->dates->dateTime($delivery->pickup_deadline_at, $order->locale);
    }

    /** @param list<DigitalGrantData> $grants */
    public function digitalAccessGranted(Order $order, array $grants, string $cause): void
    {
        if ($grants === []) {
            return;
        }
        $this->queue->enqueue($this->envelope($order, 'core.digital_access_granted', [
            'store_name' => $this->store->current()->storeName,
            'order_id' => $order->id,
            'order_number' => $order->public_number,
            'downloads' => array_map(static fn (DigitalGrantData $grant): array => [
                'filename' => $grant->originalFilename,
                'url' => route('downloads.show', ['token' => $grant->accessToken]),
            ], $grants),
        ], "order:{$order->id}:digital:{$cause}"));
    }

    public function manualMessage(Order $order, string $body, string $actorId, string $messageId): void
    {
        $this->queue->enqueue($this->envelope($order, 'core.manual_customer_message', [
            ...$this->base($order),
            'message' => trim($body),
        ], "order:{$order->id}:manual:{$messageId}", 'staff', $actorId));
    }

    /** @param array<string, mixed> $variables */
    private function envelope(Order $order, string $definition, array $variables, string $idempotencyKey, ?string $actorType = null, ?string $actorId = null): EmailEnvelope
    {
        $order->loadMissing('party');

        return new EmailEnvelope(
            $definition,
            $order->email,
            $this->recipientName($order),
            $order->locale,
            $variables,
            $idempotencyKey,
            'order',
            $order->id,
            $order->mode,
            actorType: $actorType,
            actorId: $actorId,
        );
    }

    /** @return array{store_name:string,order_id:string,total:string,currency:string,total_formatted:string,order_url:string} */
    private function base(Order $order): array
    {
        return [
            'store_name' => $this->store->current()->storeName,
            'order_id' => $order->id,
            'order_number' => $order->public_number,
            'total' => (string) $order->total_gross_amount,
            'currency' => $order->currency_code,
            'total_formatted' => $this->money->format((string) $order->total_gross_amount, $order->currency_code, $order->locale),
            'order_url' => $this->orderUrl($order),
        ];
    }

    private function orderUrl(Order $order): string
    {
        if ($order->customer_account_id !== null) {
            return route('storefront.account.orders.show', ['locale' => $order->locale, 'order' => $order->id]);
        }

        return route('storefront.orders.guest.show', ['locale' => $order->locale, 'token' => $this->guestAccess->issue($order->id)]);
    }

    private function recipientName(Order $order): ?string
    {
        $name = $order->party?->billing_address['recipient_name'] ?? null;
        $name = is_string($name) ? trim($name) : '';

        return $name === '' ? $order->party?->company_name : $name;
    }
}
