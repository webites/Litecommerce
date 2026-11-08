<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\I18n\CountryDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Delivery\Application\ManageDeliveryMethods;
use Litecommerce\Modules\Delivery\Application\ManageShippingMethodSettings;
use Litecommerce\Modules\Delivery\Contracts\DeliveryKind;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodRegistry;
use Litecommerce\Modules\Delivery\Infrastructure\ConfiguredShippingMethod;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethod;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethodCurrencyOverride;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryPickupPoint;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryRegionRule;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryRegionRuleCurrencyOverride;
use Litecommerce\Modules\Delivery\Infrastructure\ShippingMethodSettingsResolver;
use Litecommerce\Modules\Pricing\Contracts\PricingSettingsCatalog;
use Litecommerce\Modules\Pricing\Contracts\TaxRateCatalog;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;

final class DeliveryController extends Controller
{
    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly TaxRateCatalog $taxRates,
        private readonly PricingSettingsCatalog $pricingSettings,
        private readonly ShippingMethodRegistry $shippingMethods,
        private readonly ShippingMethodSettingsResolver $methodSettings,
    ) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('delivery', $this->settingsData(
            $this->currencies,
            $this->taxRates,
            $this->pricingSettings,
        ));
    }

    /** @return array<string, mixed> */
    private function settingsData(CurrencyRepository $currencies, TaxRateCatalog $taxRates, PricingSettingsCatalog $settings): array
    {
        $methods = DeliveryMethod::query()
            ->with(['currencyOverrides', 'regionRules.currencyOverrides', 'pickupPoints'])
            ->orderBy('position')
            ->get();

        return [
            'methods' => $methods->map($this->presentMethod(...))->all(),
            'currencies' => array_map(static fn ($currency): array => [
                'code' => $currency->code, 'name' => $currency->name, 'is_base' => $currency->isBase,
            ], $currencies->all()),
            'tax_rates' => array_map(static fn ($rate): array => ['id' => $rate->id, 'name' => $rate->name], $taxRates->all()),
            'price_entry_mode' => $settings->entryMode()->value,
            // Unfiltered — unlike the storefront checkout picker, the merchant
            // must be able to pick ANY country to configure a rate/exclusion for it.
            'countries' => (new CountryDirectory)->all(app()->getLocale()),
            'plugin_methods' => $this->pluginMethods(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pluginMethods(): array
    {
        $methods = array_values(array_filter(
            $this->shippingMethods->methods(),
            static fn ($method): bool => $method->descriptor()->providerId !== ConfiguredShippingMethod::PROVIDER,
        ));
        $settings = $this->methodSettings->resolve($methods);

        return array_map(static function ($method) use ($settings): array {
            $descriptor = $method->descriptor();
            $setting = $settings[$descriptor->id];

            return [
                'id' => $descriptor->id, 'provider_id' => $descriptor->providerId, 'name' => $descriptor->name,
                'kind' => $descriptor->kind->value,
                'can_cash_on_delivery' => $descriptor->supportsCashOnDelivery,
                'can_pay_on_pickup' => $descriptor->supportsPayOnPickup && $descriptor->kind === DeliveryKind::Pickup,
                'active' => $setting->active, 'tax_rate_id' => $setting->taxRateId,
                'allow_cash_on_delivery' => $setting->allowCashOnDelivery, 'allow_pay_on_pickup' => $setting->allowPayOnPickup,
                'pickup_deadline_days' => $setting->pickupDeadlineDays,
            ];
        }, $methods);
    }

    public function updatePluginMethod(Request $request, string $methodId, ManageShippingMethodSettings $manager): RedirectResponse
    {
        abort_unless($this->shippingMethods->has($methodId) && ! str_starts_with($methodId, ConfiguredShippingMethod::PROVIDER.'.'), 404);

        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'allow_cash_on_delivery' => ['required', 'boolean'],
            'allow_pay_on_pickup' => ['required', 'boolean'],
            'pickup_deadline_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $manager->update($methodId, $data);

        return back()->with('status', trans('delivery::panel.method_saved'));
    }

    public function store(Request $request, ManageDeliveryMethods $manager): RedirectResponse
    {
        $data = $this->validated($request);
        $manager->create($data);

        return back()->with('status', trans('delivery::panel.method_added'));
    }

    public function update(Request $request, DeliveryMethod $deliveryMethod, ManageDeliveryMethods $manager): RedirectResponse
    {
        $data = $this->validated($request);
        $manager->update($deliveryMethod, $data);

        return back()->with('status', trans('delivery::panel.method_saved'));
    }

    public function destroy(DeliveryMethod $deliveryMethod, ManageDeliveryMethods $manager): RedirectResponse
    {
        $manager->delete($deliveryMethod);

        return back()->with('status', trans('delivery::panel.method_removed'));
    }

    public function updateRegionRules(Request $request, DeliveryMethod $deliveryMethod, ManageDeliveryMethods $manager): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['present', 'array'],
            'rules.*.rule_type' => ['required', 'in:exclude,country_rate,continent_rate'],
            'rules.*.country_code' => ['nullable', 'string', 'size:2'],
            'rules.*.continent_code' => ['nullable', 'string', 'size:2'],
            'rules.*.price_amount' => ['nullable', 'numeric', 'min:0'],
            'rules.*.currency_overrides' => ['array'],
            'rules.*.currency_overrides.*.currency_code' => ['required', 'string', 'size:3'],
            'rules.*.currency_overrides.*.price_amount' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data['rules'] as $rule) {
            if ($rule['rule_type'] !== 'exclude' && $rule['price_amount'] === null) {
                return back()->withErrors(['rules' => trans('delivery::panel.region_rate_requires_price')]);
            }
        }

        $manager->replaceRegionRules($deliveryMethod, array_map(static fn (array $rule): array => [
            'rule_type' => $rule['rule_type'],
            'country_code' => $rule['country_code'] ?? null,
            'continent_code' => $rule['continent_code'] ?? null,
            'price_amount' => $rule['rule_type'] === 'exclude' ? null : $rule['price_amount'],
            'currency_overrides' => $rule['currency_overrides'] ?? [],
        ], $data['rules']));

        return back()->with('status', trans('delivery::panel.regions_saved'));
    }

    public function updateCurrencyOverride(Request $request, DeliveryMethod $deliveryMethod, string $currencyCode, ManageDeliveryMethods $manager): RedirectResponse
    {
        $data = $request->validate([
            'price_amount' => ['nullable', 'numeric', 'min:0'],
            'free_threshold_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $manager->setCurrencyOverride($deliveryMethod, strtoupper($currencyCode), $data['price_amount'] ?? null, $data['free_threshold_amount'] ?? null);

        return back()->with('status', trans('delivery::panel.currency_override_saved'));
    }

    public function storePickupPoint(Request $request, DeliveryMethod $deliveryMethod, ManageDeliveryMethods $manager): RedirectResponse
    {
        $manager->addPickupPoint($deliveryMethod, $this->validatedPickupPoint($request));

        return back()->with('status', trans('delivery::panel.pickup_point_added'));
    }

    public function updatePickupPoint(Request $request, DeliveryMethod $deliveryMethod, DeliveryPickupPoint $pickupPoint, ManageDeliveryMethods $manager): RedirectResponse
    {
        $manager->updatePickupPoint($pickupPoint, $this->validatedPickupPoint($request));

        return back()->with('status', trans('delivery::panel.pickup_point_saved'));
    }

    public function destroyPickupPoint(DeliveryMethod $deliveryMethod, DeliveryPickupPoint $pickupPoint, ManageDeliveryMethods $manager): RedirectResponse
    {
        $manager->removePickupPoint($pickupPoint);

        return back()->with('status', trans('delivery::panel.pickup_point_removed'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['flat_rate', 'pickup'])],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'price_amount' => ['nullable', 'numeric', 'min:0'],
            'free_threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'supports_cash_on_delivery' => ['required', 'boolean'],
            'supports_pay_on_pickup' => ['required', 'boolean'],
            'pickup_deadline_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        // "płatność na miejscu ... wymaga odbioru osobistego" — only a
        // pickup configuration may declare this capability.
        if ($data['kind'] !== 'pickup') {
            $data['supports_pay_on_pickup'] = false;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validatedPickupPoint(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'array'],
            'instructions.*' => ['nullable', 'string', 'max:2000'],
            'active' => ['required', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentMethod(DeliveryMethod $method): array
    {
        return [
            'id' => $method->id, 'kind' => $method->kind, 'name' => $method->name,
            'active' => $method->active, 'position' => $method->position,
            'price_amount' => self::trimDecimal($method->price_amount), 'free_threshold_amount' => self::trimDecimal($method->free_threshold_amount),
            'tax_rate_id' => $method->tax_rate_id,
            'supports_cash_on_delivery' => $method->supports_cash_on_delivery,
            'supports_pay_on_pickup' => $method->supports_pay_on_pickup,
            'pickup_deadline_days' => $method->pickup_deadline_days,
            'currency_overrides' => $method->currencyOverrides->map(static fn (DeliveryMethodCurrencyOverride $o): array => [
                'currency_code' => $o->currency_code, 'price_amount' => self::trimDecimal($o->price_amount), 'free_threshold_amount' => self::trimDecimal($o->free_threshold_amount),
            ])->all(),
            'region_rules' => $method->regionRules->map(static fn (DeliveryRegionRule $rule): array => [
                'id' => $rule->id, 'rule_type' => $rule->rule_type,
                'country_code' => $rule->country_code, 'continent_code' => $rule->continent_code,
                'price_amount' => self::trimDecimal($rule->price_amount),
                'currency_overrides' => $rule->currencyOverrides->map(static fn (DeliveryRegionRuleCurrencyOverride $o): array => [
                    'currency_code' => $o->currency_code, 'price_amount' => self::trimDecimal($o->price_amount),
                ])->all(),
            ])->all(),
            'pickup_points' => $method->pickupPoints->map(static fn (DeliveryPickupPoint $point): array => [
                'id' => $point->id, 'name' => $point->name, 'country_code' => $point->country_code,
                'postal_code' => $point->postal_code, 'city' => $point->city, 'region' => $point->region,
                'address_line_1' => $point->address_line_1, 'address_line_2' => $point->address_line_2,
                'instructions' => $point->instructions ?? [], 'active' => $point->active,
            ])->all(),
        ];
    }

    /**
     * Amounts are stored at a fixed 6-decimal precision; trims the display
     * copy back to what was actually entered, e.g. "14.990000" -> "14.99".
     */
    private static function trimDecimal(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
