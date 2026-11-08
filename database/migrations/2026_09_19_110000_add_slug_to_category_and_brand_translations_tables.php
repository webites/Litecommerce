<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Same rationale/shape as add_slug_to_product_translations_table: nullable
        // at the schema level, unique per locale, backfilled below for rows that
        // predate this migration.
        Schema::table('category_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        Schema::table('brand_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        $this->backfill('category_translations', 'kategoria');
        $this->backfill('brand_translations', 'marka');

        Schema::table('category_translations', function (Blueprint $table): void {
            $table->unique(['locale', 'slug']);
        });

        Schema::table('brand_translations', function (Blueprint $table): void {
            $table->unique(['locale', 'slug']);
        });
    }

    private function backfill(string $table, string $fallbackBase): void
    {
        $seen = [];
        foreach (DB::table($table)->select('id', 'locale', 'name')->get() as $row) {
            $base = Str::slug($row->name) ?: $fallbackBase;
            $slug = $base;
            for ($suffix = 2; $seen[$row->locale][$slug] ?? false; $suffix++) {
                $slug = "{$base}-{$suffix}";
            }
            $seen[$row->locale][$slug] = true;
            DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('category_translations', function (Blueprint $table): void {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('slug');
        });

        Schema::table('brand_translations', function (Blueprint $table): void {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
