<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Catalog\Infrastructure\Models\CatalogSeoSettings;

final class UpdateCatalogSeoSettings
{
    public function handle(string $defaultVariantSeoMode): void
    {
        CatalogSeoSettings::current()->update(['default_variant_seo_mode' => $defaultVariantSeoMode]);

        // Every product without its own override inherits this — the
        // sitemap's variant enumeration and canonical/robots tags both
        // depend on it, so a change here invalidates every locale's cache,
        // not just one product's.
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));
    }
}
