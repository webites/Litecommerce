<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessGrantor;
use Litecommerce\Modules\Digital\Contracts\DigitalGrantData;
use Litecommerce\Modules\Orders\Contracts\DigitalGrantRefusedException as Refused;
use Litecommerce\Modules\Orders\Contracts\OrderDigitalAccessGranting;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderOutboxEvent;

final class GrantDigitalAccessAfterLatePayment implements OrderDigitalAccessGranting
{
    public function __construct(
        private readonly DigitalAccessGrantor $grantor,
        private readonly DigitalAccessCatalog $catalog,
        private readonly OrderEmailComposer $emails,
        private readonly ProductTypeRegistry $productTypes,
    ) {}

    public function isEligible(Order $order): bool
    {
        return $order->payment_status === OrderPaymentStatus::LatePayment->value
            && $order->mode !== 'test'
            && $order->lines->contains(fn ($line): bool => $this->productTypes->get($line->product_type)->grantsDigitalAccess);
    }

    public function grantAfterLatePayment(string $orderId, string $actorId, string $reason): int
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new Refused(Refused::REASON_REQUIRED, 'Podaj powód przyznania dostępu.');
        }

        return DB::transaction(function () use ($orderId, $actorId, $reason): int {
            $order = Order::query()->with('lines')->lockForUpdate()->findOrFail($orderId);
            $this->assertEligible($order);

            $before = array_map(static fn (DigitalGrantData $grant): int => $grant->id, $this->catalog->findForOrder($order->id));
            $grants = $this->grantAll($order);

            if ($grants === []) {
                throw new Refused(Refused::NO_PURCHASABLE_FILE, 'Produkty cyfrowe w zamówieniu nie mają aktywnego pliku do udostępnienia.');
            }

            $created = count(array_filter($grants, static fn (DigitalGrantData $grant): bool => ! in_array($grant->id, $before, true)));

            if ($created === 0) {
                return 0;
            }

            $order->update(['version' => $order->version + 1]);
            OrderOutboxEvent::query()->create([
                'id' => (string) Str::uuid(), 'order_id' => $order->id, 'event_type' => 'DigitalAccessGrantedManually',
                'aggregate_version' => $order->version, 'occurred_at' => now(), 'available_at' => now(),
                'payload' => ['actor_id' => $actorId, 'reason' => $reason, 'grant_ids' => array_map(static fn (DigitalGrantData $grant): int => $grant->id, $grants)],
            ]);
            $this->emails->digitalAccessGranted($order, $grants, 'manual-'.substr(sha1(implode(',', array_map(static fn (DigitalGrantData $grant): int => $grant->id, $grants))), 0, 12));

            return $created;
        });
    }

    private function assertEligible(Order $order): void
    {
        if ($order->payment_status !== OrderPaymentStatus::LatePayment->value) {
            throw new Refused(Refused::NOT_LATE_PAYMENT, 'Dostęp można przyznać ręcznie tylko po spóźnionej wpłacie.');
        }

        if ($order->mode === 'test') {
            throw new Refused(Refused::TEST_ORDER, 'Zamówienia testowego nie obejmuje przyznawanie dostępu.');
        }

        if (! $this->isEligible($order)) {
            throw new Refused(Refused::NO_DIGITAL_LINES, 'Zamówienie nie zawiera produktów cyfrowych.');
        }
    }

    /** @return list<DigitalGrantData> */
    private function grantAll(Order $order): array
    {
        $grants = [];

        foreach ($order->lines as $line) {
            if (! $this->productTypes->get($line->product_type)->grantsDigitalAccess) {
                continue;
            }

            array_push($grants, ...$this->grantor->grantForOrderLine(
                (int) $line->variant_id, 'order_line', (string) $line->getKey(), $order->id,
                $order->customer_account_id, $order->email, new \DateTimeImmutable,
            ));
        }

        return $grants;
    }
}
