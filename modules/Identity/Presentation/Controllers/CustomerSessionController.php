<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Litecommerce\Modules\Cart\Contracts\CartMergeAdjustment;
use Litecommerce\Modules\Cart\Contracts\CartMerger;
use Litecommerce\Modules\Cart\Contracts\CartMergeReport;
use Litecommerce\Modules\Identity\Presentation\Requests\LoginCustomerRequest;

final class CustomerSessionController extends Controller
{
    public function store(LoginCustomerRequest $request, CartMerger $cartMerger): JsonResponse
    {
        $customer = $request->authenticate();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $merge = $cartMerger->mergeGuestCartIntoAccount((int) $customer->getKey(), $request);

        return response()->json([
            'customer' => ['id' => $customer->getKey(), 'email' => $customer->email],
            'cart_merge' => $this->presentCartMerge($merge),
        ]);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
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
