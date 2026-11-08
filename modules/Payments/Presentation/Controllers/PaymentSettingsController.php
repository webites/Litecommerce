<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Payments\Infrastructure\Models\BankTransferAccount;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;

final class PaymentSettingsController extends Controller
{
    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly LanguageRepository $languages,
    ) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('payments', $this->settingsData($this->currencies, $this->languages));
    }

    /** @return array<string, mixed> */
    private function settingsData(CurrencyRepository $currencies, LanguageRepository $languages): array
    {
        $accounts = BankTransferAccount::query()->get()->keyBy('currency_code');

        return [
            'currencies' => array_map(static function ($currency) use ($accounts): array {
                $account = $accounts->get($currency->code);

                if (! $account instanceof BankTransferAccount) {
                    return [
                        'code' => $currency->code, 'name' => $currency->name,
                        'beneficiary_name' => '', 'account_number' => '', 'instructions' => [], 'enabled' => false,
                    ];
                }

                return [
                    'code' => $currency->code, 'name' => $currency->name,
                    'beneficiary_name' => $account->beneficiary_name,
                    'account_number' => $account->account_number,
                    'instructions' => $account->instructions ?? [],
                    'enabled' => $account->enabled,
                ];
            }, $currencies->all()),
            'languages' => array_map(static fn ($language): array => ['code' => $language->code, 'name' => $language->name], array_values(array_filter(
                $languages->all(), static fn ($language): bool => $language->isEnabled,
            ))),
        ];
    }

    public function update(Request $request, string $currencyCode): RedirectResponse
    {
        $data = $request->validate([
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'instructions' => ['array'],
            'instructions.*' => ['nullable', 'string', 'max:5000'],
            'enabled' => ['required', 'boolean'],
        ]);
        BankTransferAccount::query()->updateOrCreate(['currency_code' => strtoupper($currencyCode)], [
            'beneficiary_name' => $data['beneficiary_name'], 'account_number' => $data['account_number'],
            'instructions' => $data['instructions'] ?? [], 'enabled' => $data['enabled'],
        ]);

        return back()->with('status', 'Ustawienia przelewu zostały zapisane.');
    }
}
