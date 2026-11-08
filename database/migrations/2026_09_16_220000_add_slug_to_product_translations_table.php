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
        // Nullable at the schema level (no doctrine/dbal dependency for a
        // later NOT NULL ->change() — not installed in this project) even
        // though application code (Catalog\Application\SlugGenerator)
        // always sets it going forward; the unique index is the real
        // integrity guarantee. Unique per language, not globally — the
        // same slug text may exist once per locale, mirroring how "name"
        // is already scoped per (locale) rather than globally.
        Schema::table('product_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill catalogs that predate P1-013 — every future row gets a
        // slug at creation via SlugGenerator, but rows already in the
        // database (P1-003..P1-012 QA data) never went through that path.
        $seen = [];
        foreach (DB::table('product_translations')->select('id', 'locale', 'name')->get() as $row) {
            $base = Str::slug($row->name) ?: 'produkt';
            $slug = $base;
            for ($suffix = 2; $seen[$row->locale][$slug] ?? false; $suffix++) {
                $slug = "{$base}-{$suffix}";
            }
            $seen[$row->locale][$slug] = true;
            DB::table('product_translations')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('product_translations', function (Blueprint $table): void {
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table): void {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
