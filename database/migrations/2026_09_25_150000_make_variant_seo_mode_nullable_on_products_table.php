<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop+recreate rather than ->nullable()->change(): this project avoids
        // Blueprint::change() (needs doctrine/dbal, unreliable on SQLite — the
        // engine tests/CI run on — see 2026_09_19_120000_add_archetype_to_cms_pages_table.php's
        // own history for the same tradeoff). No data loss risk: this migration
        // runs right after the column was first added in this same batch of
        // work, before any real product depended on a specific value.
        // null now means "no per-product override — inherit
        // catalog_seo_settings.default_variant_seo_mode" (see UpdateCatalogSeoSettings).
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('variant_seo_mode');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('variant_seo_mode', 32)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('variant_seo_mode');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('variant_seo_mode', 32)->default('canonical_to_product')->after('status');
        });
    }
};
