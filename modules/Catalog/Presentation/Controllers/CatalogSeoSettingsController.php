<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Catalog\Application\UpdateCatalogSeoSettings;
use Litecommerce\Modules\Catalog\Contracts\VariantSeoMode;

/**
 * Only a write endpoint — the "/panel/seo" page itself (where the merchant
 * actually sees and submits this form) is Store\Presentation\Controllers\SeoSettingsController,
 * reading the current value through Catalog\Contracts\CatalogSeoSettingsRepository.
 * The write stays here because only Catalog may touch its own tables —
 * module boundaries are crossed through Contracts for reads, never for
 * writes, which always go through the owning module's own endpoint.
 */
final class CatalogSeoSettingsController extends Controller
{
    public function update(Request $request, UpdateCatalogSeoSettings $updater, AuditLogger $audit): RedirectResponse
    {
        /** @var array{default_variant_seo_mode:string} $data */
        $data = $request->validate([
            'default_variant_seo_mode' => ['required', Rule::enum(VariantSeoMode::class)],
        ]);

        $updater->handle($data['default_variant_seo_mode']);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'catalog.seo_settings.updated',
            subjectType: 'catalog_seo_settings',
            subjectId: '1',
            changes: $data,
        ));

        return back()->with('status', 'Domyślny tryb indeksowania wariantów został zapisany.');
    }
}
