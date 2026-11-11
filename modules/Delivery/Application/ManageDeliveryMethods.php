<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethod;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryPickupPoint;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryRegionRule;

/**
 * Panel-facing CRUD for delivery configurations. Lives in Application
 * rather than behind a public Contract because only this module's own
 * Presentation controller calls it — no other module manages delivery
 * configuration, they only read quotes through DeliveryMethodCatalog.
 */
final class ManageDeliveryMethods
{
    /** @param array<string, mixed> $data */
    public function create(array $data): DeliveryMethod
    {
        return DeliveryMethod::query()->create($this->methodAttributes($data));
    }

    /**
     * Creates a method together with what makes it visible to customers: the first
     * pickup point (pickup) or the region rates (courier).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $pickupPoint
     * @param  list<array{rule_type: string, country_code: ?string, continent_code: ?string, price_amount: ?string, currency_overrides: list<array{currency_code: string, price_amount: string}>}>|null  $regionRules
     */
    public function createConfigured(array $data, ?array $pickupPoint, ?array $regionRules): DeliveryMethod
    {
        return DB::transaction(function () use ($data, $pickupPoint, $regionRules): DeliveryMethod {
            $method = $this->create($data);

            if ($pickupPoint !== null) {
                $this->addPickupPoint($method, $pickupPoint);
            }

            if ($regionRules !== null) {
                $this->replaceRegionRules($method, $regionRules);
            }

            return $method;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(DeliveryMethod $method, array $data): DeliveryMethod
    {
        $method->update($this->methodAttributes($data));

        return $method->fresh();
    }

    /**
     * Saves a method together with the sections the panel edits in the same form:
     * currency overrides, region rules and pickup points. A null section is left
     * untouched; a given pickup-point list is the full set (missing ids are deleted).
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{currency_code: string, price_amount: ?string, free_threshold_amount: ?string}>|null  $currencyOverrides
     * @param  list<array{rule_type: string, country_code: ?string, continent_code: ?string, price_amount: ?string, currency_overrides: list<array{currency_code: string, price_amount: string}>}>|null  $regionRules
     * @param  list<array<string, mixed>>|null  $pickupPoints
     */
    public function save(DeliveryMethod $method, array $data, ?array $currencyOverrides, ?array $regionRules, ?array $pickupPoints): DeliveryMethod
    {
        return DB::transaction(function () use ($method, $data, $currencyOverrides, $regionRules, $pickupPoints): DeliveryMethod {
            $this->update($method, $data);

            foreach ($currencyOverrides ?? [] as $override) {
                $this->setCurrencyOverride($method, strtoupper($override['currency_code']), $override['price_amount'] ?? null, $override['free_threshold_amount'] ?? null);
            }

            if ($regionRules !== null) {
                $this->replaceRegionRules($method, $regionRules);
            }

            if ($pickupPoints !== null) {
                $this->syncPickupPoints($method, $pickupPoints);
            }

            return $method->fresh();
        });
    }

    public function delete(DeliveryMethod $method): void
    {
        $method->delete();
    }

    /**
     * Wholesale replace, in a transaction — the panel always submits the
     * full set of region rules for a method together, so there is no
     * partial-update ordering to preserve.
     *
     * @param  list<array{rule_type: string, country_code: ?string, continent_code: ?string, price_amount: ?string, currency_overrides: list<array{currency_code: string, price_amount: string}>}>  $rules
     */
    public function replaceRegionRules(DeliveryMethod $method, array $rules): void
    {
        DB::transaction(function () use ($method, $rules): void {
            DeliveryRegionRule::query()->where('delivery_method_id', $method->id)->delete();

            foreach ($rules as $rule) {
                $created = DeliveryRegionRule::query()->create([
                    'delivery_method_id' => $method->id,
                    'rule_type' => $rule['rule_type'],
                    'country_code' => $rule['country_code'],
                    'continent_code' => $rule['continent_code'],
                    'price_amount' => $rule['price_amount'],
                ]);

                foreach ($rule['currency_overrides'] as $override) {
                    $created->currencyOverrides()->create([
                        'currency_code' => $override['currency_code'],
                        'price_amount' => $override['price_amount'],
                    ]);
                }
            }
        });
    }

    public function setCurrencyOverride(DeliveryMethod $method, string $currencyCode, ?string $priceAmount, ?string $freeThresholdAmount): void
    {
        if ($priceAmount === null && $freeThresholdAmount === null) {
            $method->currencyOverrides()->where('currency_code', $currencyCode)->delete();

            return;
        }

        $method->currencyOverrides()->updateOrCreate(
            ['currency_code' => $currencyCode],
            ['price_amount' => $priceAmount, 'free_threshold_amount' => $freeThresholdAmount],
        );
    }

    /** @param array<string, mixed> $data */
    public function addPickupPoint(DeliveryMethod $method, array $data): DeliveryPickupPoint
    {
        return $method->pickupPoints()->create($this->pickupPointAttributes($data));
    }

    /** @param list<array<string, mixed>> $points */
    private function syncPickupPoints(DeliveryMethod $method, array $points): void
    {
        $keptIds = array_values(array_filter(array_map(static fn (array $point): ?int => isset($point['id']) ? (int) $point['id'] : null, $points)));
        $method->pickupPoints()->whereNotIn('id', $keptIds)->delete();

        foreach ($points as $position => $point) {
            $attributes = $this->pickupPointAttributes([...$point, 'position' => $position]);

            if (isset($point['id'])) {
                $method->pickupPoints()->whereKey((int) $point['id'])->firstOrFail()->update($attributes);
            } else {
                $method->pickupPoints()->create($attributes);
            }
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function methodAttributes(array $data): array
    {
        return [
            'kind' => $data['kind'],
            'name' => $data['name'],
            'active' => $data['active'],
            'position' => $data['position'] ?? 0,
            'price_amount' => $data['price_amount'] ?? null,
            'free_threshold_amount' => $data['free_threshold_amount'] ?? null,
            'tax_rate_id' => $data['tax_rate_id'] ?? null,
            'supports_cash_on_delivery' => $data['supports_cash_on_delivery'] ?? false,
            'supports_pay_on_pickup' => $data['supports_pay_on_pickup'] ?? false,
            'pickup_deadline_days' => ($data['kind'] ?? null) === 'pickup' ? ($data['pickup_deadline_days'] ?? null) : null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function pickupPointAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'country_code' => $data['country_code'],
            'postal_code' => $data['postal_code'],
            'city' => $data['city'],
            'region' => $data['region'] ?? null,
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'instructions' => $data['instructions'] ?? [],
            'active' => $data['active'] ?? true,
            'position' => $data['position'] ?? 0,
        ];
    }
}
