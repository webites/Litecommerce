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
        // Same rationale/shape as add_slug_to_category_and_brand_translations_tables:
        // nullable at the schema level, unique per locale, backfilled below for rows
        // that predate this migration. Powers the variant URL path segments
        // (/products/{slug}/{axis-slug}/{value-slug}/...).
        Schema::table('attribute_definition_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        Schema::table('attribute_value_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        $this->backfill('attribute_definition_translations', 'atrybut');
        $this->backfill('attribute_value_translations', 'wartosc');

        Schema::table('attribute_definition_translations', function (Blueprint $table): void {
            $table->unique(['locale', 'slug']);
        });

        Schema::table('attribute_value_translations', function (Blueprint $table): void {
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
        Schema::table('attribute_definition_translations', function (Blueprint $table): void {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('slug');
        });

        Schema::table('attribute_value_translations', function (Blueprint $table): void {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
