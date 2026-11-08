<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Litecommerce\Modules\Catalog\Contracts\VariantSeoMode;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row settings table, same shape as coupon_settings/theme_settings
        // — a product's own variant_seo_mode (nullable) overrides this default
        // when set; most products are expected to just inherit it.
        Schema::create('catalog_seo_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('default_variant_seo_mode', 32)->default(VariantSeoMode::CanonicalToProduct->value);
            $table->timestamps();
        });

        DB::table('catalog_seo_settings')->insert([
            'id' => 1,
            'default_variant_seo_mode' => VariantSeoMode::CanonicalToProduct->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_seo_settings');
    }
};
