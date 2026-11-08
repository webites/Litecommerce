<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Application;

use InvalidArgumentException;
use Litecommerce\Modules\Delivery\Contracts\DeliveryKind;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodRegistry;
use Litecommerce\Modules\Delivery\Infrastructure\ConfiguredShippingMethod;
use Litecommerce\Modules\Delivery\Infrastructure\Models\ShippingMethodSetting;

/**
 * Merchant settings for plugin-provided shipping methods. Configured
 * ("core") methods keep these settings in their own row.
 */
final class ManageShippingMethodSettings
{
    public function __construct(private readonly ShippingMethodRegistry $registry) {}

    /** @param array{active: bool, position?: int, tax_rate_id: ?int, allow_cash_on_delivery: bool, allow_pay_on_pickup: bool, pickup_deadline_days?: ?int} $data */
    public function update(string $methodId, array $data): ShippingMethodSetting
    {
        $descriptor = $this->registry->get($methodId)->descriptor();

        if ($descriptor->providerId === ConfiguredShippingMethod::PROVIDER) {
            throw new InvalidArgumentException("Method [{$methodId}] is managed through its own configuration.");
        }

        return ShippingMethodSetting::query()->updateOrCreate(['method_id' => $methodId], [
            'active' => $data['active'],
            'position' => $data['position'] ?? 0,
            'tax_rate_id' => $data['tax_rate_id'],
            'allow_cash_on_delivery' => $data['allow_cash_on_delivery'] && $descriptor->supportsCashOnDelivery,
            'allow_pay_on_pickup' => $data['allow_pay_on_pickup'] && $descriptor->supportsPayOnPickup && $descriptor->kind === DeliveryKind::Pickup,
            'pickup_deadline_days' => $descriptor->kind === DeliveryKind::Pickup ? ($data['pickup_deadline_days'] ?? null) : null,
        ]);
    }
}
