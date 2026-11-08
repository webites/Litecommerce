<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Pricing\Contracts\TaxQualificationKind;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ExchangeRate;
use Litecommerce\Modules\Pricing\Infrastructure\Models\PricingSetting;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;
use Litecommerce\Modules\Store\Contracts\CurrencyData;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;

final class PricingController extends Controller
{
    public function __construct(private readonly CurrencyRepository $currencies) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('pricing', $this->settingsData());
    }

    /** @return array<string, mixed> */
    private function settingsData(): array
    {
        $rates = ExchangeRate::query()->get()->keyBy('currency_code');
        $base = $this->currencies->base();

        return [
            'settings' => ['price_entry_mode' => PricingSetting::current()->price_entry_mode],
            'baseCurrency' => $base?->code,
            'currencies' => array_map(static fn (CurrencyData $currency): array => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimal_places' => $currency->decimalPlaces,
                'is_base' => $currency->isBase,
                'rate' => $currency->isBase ? null : self::trimDecimal($rates->get($currency->code)?->base_units_per_unit),
            ], $this->currencies->all()),
            'taxRates' => TaxRate::query()->orderBy('name')->get()->map(static fn (TaxRate $rate): array => [
                'id' => (int) $rate->getKey(),
                'name' => $rate->name,
                'kind' => $rate->kind,
                'percentage' => self::trimDecimal($rate->percentage),
            ])->values()->all(),
            'taxQualificationKinds' => [
                ['value' => TaxQualificationKind::Percentage->value, 'label' => trans('pricing::panel.tax_qualification_kinds.percentage')],
                ['value' => TaxQualificationKind::Exempt->value, 'label' => trans('pricing::panel.tax_qualification_kinds.exempt')],
                ['value' => TaxQualificationKind::NotApplicable->value, 'label' => trans('pricing::panel.tax_qualification_kinds.not_applicable')],
            ],
        ];
    }

    public function updateSettings(Request $request, AuditLogger $audit): RedirectResponse
    {
        /** @var array{price_entry_mode:string} $data */
        $data = $request->validate([
            'price_entry_mode' => ['required', 'in:net,gross'],
        ]);

        $before = PricingSetting::current()->price_entry_mode;
        PricingSetting::current()->update(['price_entry_mode' => $data['price_entry_mode']]);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'pricing.settings.updated',
            subjectType: 'pricing_setting',
            subjectId: '1',
            changes: ['before' => $before, 'after' => $data['price_entry_mode']],
        ));

        return back()->with('status', trans('pricing::panel.price_entry_mode_saved'));
    }

    public function setExchangeRate(Request $request, string $code, CurrencyRepository $currencies, AuditLogger $audit): RedirectResponse
    {
        $currency = $currencies->find(strtoupper($code));
        $base = $currencies->base();
        abort_if($currency === null, 404);
        abort_if($base !== null && $currency->code === $base->code, 422, trans('pricing::panel.base_currency_has_no_rate'));

        /** @var array{base_units_per_unit:string} $data */
        $data = $request->validate([
            'base_units_per_unit' => ['required', 'numeric', 'gt:0'],
        ]);

        ExchangeRate::query()->updateOrCreate(
            ['currency_code' => $currency->code],
            ['base_units_per_unit' => $data['base_units_per_unit']],
        );

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'pricing.exchange_rate.updated',
            subjectType: 'exchange_rate',
            subjectId: $currency->code,
            changes: ['base_units_per_unit' => $data['base_units_per_unit']],
        ));

        return back()->with('status', trans('pricing::panel.exchange_rate_saved', ['code' => $currency->code]));
    }

    public function storeTaxRate(Request $request, AuditLogger $audit): RedirectResponse
    {
        /** @var array{name:string,kind:string,percentage?:string|null} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(TaxQualificationKind::class)],
            'percentage' => [Rule::requiredIf($request->input('kind') === TaxQualificationKind::Percentage->value), 'nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $rate = TaxRate::query()->create([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'percentage' => $data['kind'] === TaxQualificationKind::Percentage->value ? $data['percentage'] : null,
        ]);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'pricing.tax_rate.created',
            subjectType: 'tax_rate',
            subjectId: (string) $rate->getKey(),
            changes: ['name' => $rate->name, 'kind' => $rate->kind, 'percentage' => $rate->percentage],
        ));

        return back()->with('status', trans('pricing::panel.tax_rate_created'));
    }

    public function updateTaxRate(Request $request, TaxRate $taxRate, AuditLogger $audit): RedirectResponse
    {
        /** @var array{name:string,kind:string,percentage?:string|null} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(TaxQualificationKind::class)],
            'percentage' => [Rule::requiredIf($request->input('kind') === TaxQualificationKind::Percentage->value), 'nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $before = ['name' => $taxRate->name, 'kind' => $taxRate->kind, 'percentage' => $taxRate->percentage];
        $taxRate->update([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'percentage' => $data['kind'] === TaxQualificationKind::Percentage->value ? $data['percentage'] : null,
        ]);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'pricing.tax_rate.updated',
            subjectType: 'tax_rate',
            subjectId: (string) $taxRate->getKey(),
            changes: ['before' => $before, 'after' => ['name' => $taxRate->name, 'kind' => $taxRate->kind, 'percentage' => $taxRate->percentage]],
        ));

        return back()->with('status', trans('pricing::panel.tax_rate_saved'));
    }

    /**
     * Amounts are stored at a fixed 6-decimal precision; trims the display
     * copy back to what was actually entered, e.g. "4.300000" -> "4.3".
     */
    private static function trimDecimal(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
