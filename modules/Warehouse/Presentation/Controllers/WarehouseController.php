<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Catalog\Contracts\VariantSummaryData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;

final class WarehouseController extends Controller
{
    public function __construct(private readonly ProductTypeRegistry $productTypes) {}

    public function index(Request $request, VariantCatalog $variants, StockLevelCatalog $stockLevels, LanguageRepository $languages): Response
    {
        $locale = $this->defaultLocale($languages);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $listing = $variants->paginateStockTracked($locale, $perPage, $page);
        $stock = $stockLevels->findMany(array_map(static fn (VariantSummaryData $v): int => $v->id, $listing['items']));

        $rows = array_map(function (VariantSummaryData $variant) use ($stock): array {
            $item = $stock[$variant->id] ?? null;

            if ($item === null) {
                return [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->productId,
                    'sku' => $variant->sku,
                    'name' => $variant->displayName,
                    'enabled' => $variant->enabled,
                    'on_hand_quantity' => '0',
                    'reserved_quantity' => '0',
                    'available_quantity' => '0',
                    'allow_backorder' => false,
                ];
            }

            return [
                'variant_id' => $variant->id,
                'product_id' => $variant->productId,
                'sku' => $variant->sku,
                'name' => $variant->displayName,
                'enabled' => $variant->enabled,
                'on_hand_quantity' => $this->trimDecimal($item->onHandQuantity),
                'reserved_quantity' => $this->trimDecimal($item->reservedQuantity),
                'available_quantity' => $this->trimDecimal($item->availableQuantity),
                'allow_backorder' => $item->allowBackorder,
            ];
        }, $listing['items']);

        $paginator = new LengthAwarePaginator(
            $rows,
            $listing['total'],
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Panel/Warehouse/Index', [
            'items' => $paginator,
        ]);
    }

    public function updateBackorder(Request $request, int $variantId, VariantCatalog $variants, StockAdjuster $adjuster, AuditLogger $audit): RedirectResponse
    {
        $this->assertStockTrackedVariantExists($variantId, $variants);
        /** @var array{allow_backorder:bool} $validated */
        $validated = $request->validate(['allow_backorder' => ['required', 'boolean']]);

        $adjuster->setAllowBackorder($variantId, $validated['allow_backorder']);
        $this->audit($request, $audit, 'warehouse.stock_item.backorder_updated', $variantId, ['allow_backorder' => $validated['allow_backorder']]);

        return back()->with('status', trans('warehouse::messages.backorder_updated'));
    }

    public function receive(Request $request, int $variantId, VariantCatalog $variants, StockAdjuster $adjuster, AuditLogger $audit): RedirectResponse
    {
        $this->assertStockTrackedVariantExists($variantId, $variants);
        /** @var array{quantity:string,reason:string|null} $validated */
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $adjuster->receive($variantId, $validated['quantity'], $validated['reason'] ?? null, (string) $request->user()?->getAuthIdentifier());
        $this->audit($request, $audit, 'warehouse.stock_item.received', $variantId, ['quantity' => $validated['quantity']]);

        return back()->with('status', trans('warehouse::messages.received'));
    }

    public function correct(Request $request, int $variantId, VariantCatalog $variants, StockAdjuster $adjuster, AuditLogger $audit): RedirectResponse
    {
        $this->assertStockTrackedVariantExists($variantId, $variants);
        /** @var array{on_hand_quantity:string,reason:string|null} $validated */
        $validated = $request->validate([
            'on_hand_quantity' => ['required', 'numeric', 'gte:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $adjuster->correctOnHand($variantId, $validated['on_hand_quantity'], $validated['reason'] ?? null, (string) $request->user()?->getAuthIdentifier());
        $this->audit($request, $audit, 'warehouse.stock_item.corrected', $variantId, ['on_hand_quantity' => $validated['on_hand_quantity']]);

        return back()->with('status', trans('warehouse::messages.corrected'));
    }

    /**
     * These actions take a raw variant id from the URL, not a route-model
     * bound Catalog entity (Warehouse only knows Catalog through its
     * Contracts) — reject an unknown or id of a variant that does not track stock as a clean 404
     * instead of letting AdjustStock's InvalidArgumentException surface
     * as a 500.
     */
    private function assertStockTrackedVariantExists(int $variantId, VariantCatalog $variants): void
    {
        $variant = $variants->find($variantId);
        abort_if($variant === null || ! $this->productTypes->get($variant->productType)->tracksStock, 404);
    }

    /**
     * Quantities are stored at a fixed 6-decimal precision; trims the
     * display copy back to what was actually entered, e.g.
     * "12.000000" -> "12".
     */
    private function trimDecimal(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }

    private function defaultLocale(LanguageRepository $languages): string
    {
        $language = $languages->default();

        return $language === null ? (string) config('app.locale', 'pl') : $language->code;
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, AuditLogger $audit, string $action, int $variantId, array $changes): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'product_variant',
            subjectId: (string) $variantId,
            changes: $changes,
        ));
    }
}
