<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Cart\Application\PriceLines;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountLookup;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

/**
 * Read-only preview per "Podgląd w panelu, bez automatycznych
 * przypomnień" in docs/knowledge-base/05-orders.md — no mutation actions
 * here, staff only observes. "Abandoned" is derived at read time rather
 * than stored, so it is always accurate without a cron dependency.
 */
final class CartOverviewController extends Controller
{
    public function index(Request $request, CustomerAccountLookup $customers): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $query = Cart::query()->whereIn('status', ['active', 'checked_out'])->orderByDesc('last_activity_at');
        $total = (clone $query)->count();
        $carts = $query->withCount('items')->forPage($page, $perPage)->get();

        $accountIds = $carts->pluck('customer_account_id')->filter()->unique()->values()->all();
        $emails = $customers->emailsFor($accountIds);
        $abandonAfter = now()->subHours((int) config('cart.abandon_after_hours'));

        $rows = $carts->map(function (Cart $cart) use ($emails, $abandonAfter): array {
            $isAbandoned = $cart->status === 'active' && $cart->last_activity_at->lt($abandonAfter);

            return [
                'id' => $cart->id,
                'owner' => $cart->customer_account_id !== null
                    ? ($emails[$cart->customer_account_id] ?? "Konto #{$cart->customer_account_id}")
                    : 'Gość',
                'currency_code' => $cart->currency_code,
                'items_count' => $cart->items_count,
                'status' => $cart->status === 'checked_out' ? 'checked_out' : ($isAbandoned ? 'abandoned' : 'active'),
                'last_activity_at' => $cart->last_activity_at->toIso8601String(),
            ];
        })->all();

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Panel/Cart/Index', ['carts' => $paginator]);
    }

    public function show(
        int $cartId,
        CustomerAccountLookup $customers,
        LanguageRepository $languages,
        PriceLines $priceLines,
    ): Response|RedirectResponse {
        $cart = Cart::query()->with('items')->find($cartId);

        if ($cart === null) {
            return $this->cartNotFound();
        }

        $defaultLanguage = $languages->default();
        $locale = $defaultLanguage === null ? 'pl' : $defaultLanguage->code;
        $priced = $priceLines->price($cart->items, $cart->currency_code, $locale, $cart->id);
        $abandonAfter = now()->subHours((int) config('cart.abandon_after_hours'));

        $drafts = CheckoutDraft::query()
            ->where('cart_id', $cart->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (CheckoutDraft $draft): array => [
                'id' => $draft->id,
                'status' => $draft->status,
                'email' => $draft->email,
                'buyer_type' => $draft->buyer_type,
                'company_name' => $draft->company_name,
                'total_gross_amount' => $draft->price_snapshot['grand_total_gross_amount'] ?? $draft->price_snapshot['total_gross_amount'] ?? null,
                'currency_code' => $draft->currency_code,
                'created_at' => $draft->created_at?->toIso8601String(),
                'confirmed_at' => $draft->confirmed_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Panel/Cart/Show', [
            'cart' => [
                'id' => $cart->id,
                'owner' => $cart->customer_account_id !== null
                    ? ($customers->emailFor($cart->customer_account_id) ?? "Konto #{$cart->customer_account_id}")
                    : 'Gość',
                'currency_code' => $cart->currency_code,
                'status' => $this->displayStatus($cart, $abandonAfter),
                'version' => $cart->version,
                'created_at' => $cart->created_at?->toIso8601String(),
                'last_activity_at' => $cart->last_activity_at->toIso8601String(),
                'all_available' => $priced->allAvailable,
                'total_net_amount' => $priced->totalNetAmount,
                'total_vat_amount' => $priced->totalVatAmount,
                'total_gross_amount' => $priced->totalGrossAmount,
                'lines' => array_map(static fn ($line): array => [
                    'variant_id' => $line->variantId,
                    'sku' => $line->sku,
                    'name' => $line->displayName,
                    'product_type' => $line->productType,
                    'quantity' => $line->quantity,
                    'unit_amount' => $line->unitAmount,
                    'line_net_amount' => $line->lineNetAmount,
                    'line_vat_amount' => $line->lineVatAmount,
                    'line_gross_amount' => $line->lineGrossAmount,
                    'available' => $line->available,
                    'unavailable_reason' => $line->unavailableReason,
                ], $priced->lines),
                'checkout_drafts' => $drafts,
            ],
        ]);
    }

    private function cartNotFound(): RedirectResponse
    {
        return redirect()->route('panel.cart.index')->with('error', 'Koszyk nie został znaleziony.');
    }

    private function displayStatus(Cart $cart, Carbon $abandonAfter): string
    {
        if ($cart->status === 'checked_out') {
            return 'checked_out';
        }

        return $cart->last_activity_at->lt($abandonAfter) ? 'abandoned' : 'active';
    }
}
