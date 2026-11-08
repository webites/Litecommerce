<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Coupons\Application\ManageCoupons;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponLedgerEntry;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponReservation;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponSettings;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountLookup;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

final class CouponController extends Controller
{
    public function index(Request $request, CurrencyRepository $currencies, CustomerAccountLookup $customers): Response
    {
        $coupons = Coupon::query()->orderByDesc('id')->paginate(20)->withQueryString();
        $accountIds = collect($coupons->items())->pluck('customer_account_id')->filter()->unique()->values()->all();
        $emails = $customers->emailsFor($accountIds);

        return Inertia::render('Panel/Coupons/Index', [
            'coupons' => $coupons->through(fn (Coupon $coupon): array => $this->present($coupon, $emails))->toArray(),
            'base_currency_code' => $currencies->base()?->code,
            'settings' => ['enabled' => CouponSettings::current()->enabled],
        ]);
    }

    public function show(int $coupon, CustomerAccountLookup $customers, CurrencyRepository $currencies): Response|RedirectResponse
    {
        $coupon = Coupon::find($coupon);

        if ($coupon === null) {
            return $this->couponNotFound();
        }

        $ledger = CouponLedgerEntry::query()->where('coupon_id', $coupon->getKey())->orderByDesc('occurred_at')->orderByDesc('id')->limit(200)->get();
        $reservations = CouponReservation::query()->where('coupon_id', $coupon->getKey())->orderByDesc('id')->limit(100)->get();
        $email = $coupon->customer_account_id === null ? null : $customers->emailFor($coupon->customer_account_id);

        return Inertia::render('Panel/Coupons/Show', [
            'coupon' => $this->present($coupon, $email === null ? [] : [$coupon->customer_account_id => $email]),
            'base_currency_code' => $currencies->base()?->code,
            'ledger' => $ledger->map(static fn (CouponLedgerEntry $entry): array => [
                'id' => $entry->id, 'type' => $entry->type, 'amount' => self::trimDecimal((string) $entry->amount),
                'reference_type' => $entry->reference_type, 'reference_id' => $entry->reference_id,
                'reason' => $entry->reason, 'actor_id' => $entry->actor_id,
                'occurred_at' => $entry->occurred_at->toIso8601String(),
            ])->all(),
            'reservations' => $reservations->map(static fn (CouponReservation $reservation): array => [
                'id' => $reservation->id, 'amount' => self::trimDecimal((string) $reservation->amount), 'state' => $reservation->state,
                'reference_type' => $reservation->reference_type, 'reference_id' => $reservation->reference_id,
                'expires_at' => $reservation->expires_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function store(Request $request, ManageCoupons $manager): RedirectResponse
    {
        $data = $this->validated($request, null);
        $manager->create($data, (string) $request->user()?->getAuthIdentifier());

        return back()->with('status', trans('coupons::panel.created'));
    }

    public function update(Request $request, Coupon $coupon, ManageCoupons $manager): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        $manager->update($coupon, $data);

        return back()->with('status', trans('coupons::panel.saved'));
    }

    public function topUp(Request $request, Coupon $coupon, ManageCoupons $manager): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string', 'max:500']]);
        $manager->topUp($coupon, (string) $data['amount'], (string) $request->user()?->getAuthIdentifier(), $data['reason'] ?? null);

        return back()->with('status', trans('coupons::panel.balance_topped_up'));
    }

    public function resetPin(Coupon $coupon, ManageCoupons $manager): RedirectResponse
    {
        $manager->resetPinAttempts($coupon);

        return back()->with('status', trans('coupons::panel.pin_attempts_reset'));
    }

    public function updateSettings(Request $request, ManageCoupons $manager): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $manager->updateSettings((bool) $data['enabled']);

        return back()->with('status', trans('coupons::panel.settings_saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Coupon $coupon): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon)],
            'status' => [$coupon === null ? 'nullable' : 'required', Rule::in(['active', 'blocked'])],
            'customer_account_id' => ['nullable', 'integer', 'exists:customer_accounts,id'],
            'pin' => ['nullable', 'string', 'min:4', 'max:32'],
            'expires_at' => ['nullable', 'date'],
            'combinable_with_promotional' => ['required', 'boolean'],
            'combinable_with_other_coupons' => ['required', 'boolean'],
            'applies_to_delivery' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($coupon !== null) {
            unset($data['initial_balance']);
        } else {
            unset($data['status']);
        }

        return $data;
    }

    private function couponNotFound(): RedirectResponse
    {
        return redirect()->route('panel.coupons.index')->with('error', 'Kupon nie został znaleziony.');
    }

    /** @param  array<int, string>  $emails */
    private function present(Coupon $coupon, array $emails): array
    {
        return [
            'id' => $coupon->id, 'code' => $coupon->code, 'status' => $coupon->status,
            'customer_account_id' => $coupon->customer_account_id,
            'customer_email' => $coupon->customer_account_id === null ? null : ($emails[$coupon->customer_account_id] ?? null),
            'has_pin' => $coupon->pin_hash !== null,
            'failed_pin_attempts' => $coupon->failed_pin_attempts,
            'pin_locked' => $coupon->isPinLocked(),
            'expires_at' => $coupon->expires_at?->toIso8601String(),
            'is_expired' => $coupon->isExpired(),
            'combinable_with_promotional' => $coupon->combinable_with_promotional,
            'combinable_with_other_coupons' => $coupon->combinable_with_other_coupons,
            'applies_to_delivery' => $coupon->applies_to_delivery,
            'balance' => self::trimDecimal((string) $coupon->balance),
            'reserved_amount' => self::trimDecimal((string) $coupon->reserved_amount),
            'available_balance' => self::trimDecimal($coupon->availableBalance()),
            'note' => $coupon->note,
            'created_at' => $coupon->created_at?->toIso8601String(),
        ];
    }

    /** Amounts are stored at a fixed 6-decimal precision; trims the display copy, e.g. "50.000000" -> "50". */
    private static function trimDecimal(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
