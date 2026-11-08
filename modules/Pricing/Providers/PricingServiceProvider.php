<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Providers;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Pricing\Application\ConfigurePricing;
use Litecommerce\Modules\Pricing\Application\PricingDemoDataContributor;
use Litecommerce\Modules\Pricing\Application\QuoteVariantPrice;
use Litecommerce\Modules\Pricing\Contracts\Events\PricingIndexChanged;
use Litecommerce\Modules\Pricing\Contracts\ExchangeRateCatalog;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Pricing\Contracts\PricingSettingsCatalog;
use Litecommerce\Modules\Pricing\Contracts\PricingSetup;
use Litecommerce\Modules\Pricing\Contracts\ProductTaxAssignmentCatalog;
use Litecommerce\Modules\Pricing\Contracts\ProductTaxAssignmentManager;
use Litecommerce\Modules\Pricing\Contracts\TaxRateCatalog;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceCatalog;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceManager;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ExchangeRate;
use Litecommerce\Modules\Pricing\Infrastructure\Models\PricingSetting;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ProductTaxAssignment;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPrice;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPriceOverride;
use Litecommerce\Modules\Pricing\Infrastructure\Repositories\EloquentExchangeRateCatalog;
use Litecommerce\Modules\Pricing\Infrastructure\Repositories\EloquentPricingSettingsCatalog;
use Litecommerce\Modules\Pricing\Infrastructure\Repositories\EloquentProductTaxAssignmentCatalog;
use Litecommerce\Modules\Pricing\Infrastructure\Repositories\EloquentProductTaxAssignmentManager;
use Litecommerce\Modules\Pricing\Infrastructure\Repositories\EloquentTaxRateCatalog;
use Litecommerce\Modules\Pricing\Infrastructure\Repositories\EloquentVariantPriceCatalog;
use Litecommerce\Modules\Pricing\Infrastructure\Repositories\EloquentVariantPriceManager;
use Litecommerce\Modules\Pricing\Presentation\Controllers\PricingController;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;

final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaxRateCatalog::class, EloquentTaxRateCatalog::class);
        $this->app->bind(ExchangeRateCatalog::class, EloquentExchangeRateCatalog::class);
        $this->app->bind(PricingSettingsCatalog::class, EloquentPricingSettingsCatalog::class);
        $this->app->bind(PricingSetup::class, ConfigurePricing::class);
        $this->app->bind(ProductTaxAssignmentCatalog::class, EloquentProductTaxAssignmentCatalog::class);
        $this->app->bind(ProductTaxAssignmentManager::class, EloquentProductTaxAssignmentManager::class);
        $this->app->bind(VariantPriceCatalog::class, EloquentVariantPriceCatalog::class);
        $this->app->bind(VariantPriceManager::class, EloquentVariantPriceManager::class);
        $this->app->bind(PriceQuoter::class, QuoteVariantPrice::class);
        $this->app->singleton(PricingDemoDataContributor::class);
        $this->app->tag(PricingDemoDataContributor::class, DemoDataContributor::class);
    }

    public function boot(): void
    {
        $this->app->make(AuditRetentionRegistry::class)->keepIndefinitely('pricing.');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pricing');

        foreach ([VariantPrice::class, VariantPriceOverride::class] as $model) {
            $model::saved(static fn ($price) => DB::afterCommit(fn () => CommittedEvent::dispatch(new PricingIndexChanged([(int) $price->variant_id]))));
            $model::deleted(static fn ($price) => DB::afterCommit(fn () => CommittedEvent::dispatch(new PricingIndexChanged([(int) $price->variant_id]))));
        }
        foreach ([ExchangeRate::class, PricingSetting::class, ProductTaxAssignment::class, TaxRate::class] as $model) {
            $model::saved(static fn () => DB::afterCommit(fn () => CommittedEvent::dispatch(new PricingIndexChanged)));
            $model::deleted(static fn () => DB::afterCommit(fn () => CommittedEvent::dispatch(new PricingIndexChanged)));
        }

        $this->app->make(SettingsTabRegistry::class)->register(
            'pricing',
            'store::tabs.pricing',
            150,
            [$this->app->make(PricingController::class), 'settingsTab'],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManagePricing->value),
        );
    }
}
