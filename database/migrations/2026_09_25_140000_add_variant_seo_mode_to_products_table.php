<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Litecommerce\Modules\Catalog\Contracts\VariantSeoMode;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('variant_seo_mode', 32)->default(VariantSeoMode::CanonicalToProduct->value)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('variant_seo_mode');
        });
    }
};
