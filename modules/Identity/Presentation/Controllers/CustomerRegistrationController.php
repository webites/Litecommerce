<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Litecommerce\Modules\Cart\Contracts\CartMergeAdjustment;
use Litecommerce\Modules\Cart\Contracts\CartMerger;
use Litecommerce\Modules\Cart\Contracts\CartMergeReport;
use Litecommerce\Modules\Consents\Contracts\ConsentSource;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Identity\Presentation\Requests\RegisterCustomerRequest;

/**
 * "Zakup gościnny... Wiele adresów dostawy" in docs/knowledge-base/07-identity.md
 * assumes an account can exist — there is no panel-side way to create
 * one, so self-registration is the only path. No storefront theme
 * exists yet (P1-013): this is a JSON endpoint, not a rendered page.
 */
final class CustomerRegistrationController extends Controller
{
    public function store(RegisterCustomerRequest $request, CartMerger $cartMerger, MarketingConsentRegistry $consents): JsonResponse
    {
        $customer = CustomerAccount::query()->create([
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        $this->recordMarketingConsent($request, $customer, $consents);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $merge = $cartMerger->mergeGuestCartIntoAccount((int) $customer->getKey(), $request);

        return response()->json([
            'customer' => ['id' => $customer->getKey(), 'email' => $customer->email],
            'cart_merge' => $this->presentCartMerge($merge),
        ], 201);
    }

    private function recordMarketingConsent(RegisterCustomerRequest $request, CustomerAccount $customer, MarketingConsentRegistry $consents): void
    {
        if (! $request->boolean('marketing_consent')) {
            return;
        }

        try {
            $consents->grant($customer->email, ConsentSource::Registration, $request->input('locale'));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** @return array{merged: bool, adjustments: list<array<string, mixed>>} */
    private function presentCartMerge(CartMergeReport $merge): array
    {
        return [
            'merged' => $merge->merged,
            'adjustments' => array_map(static fn (CartMergeAdjustment $adjustment): array => [
                'variant_id' => $adjustment->variantId,
                'requested_quantity' => $adjustment->requestedQuantity,
                'adjusted_quantity' => $adjustment->adjustedQuantity,
            ], $merge->adjustments),
        ];
    }
}
