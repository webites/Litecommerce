<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            // Non-null only for archetype template rows (e.g. 'category', 'brand') —
            // the merchant's whole-archetype override, matched by Cms\Infrastructure\
            // ArchetypeResolver via Cms\Contracts\ArchetypeRegistry, never by slug
            // (an archetype row's own cms_page_translations.slug is a synthetic,
            // never-user-typeable placeholder — see GetOrCreateArchetypeTemplate).
            // Plain unique index: MySQL allows unlimited NULLs but blocks a
            // duplicate non-null value, so this alone guarantees "at most one row
            // per archetype" without the SetHomepage-style transaction (a stronger
            // guarantee isn't needed here since there's no "exactly one true"
            // boolean to coordinate).
            $table->string('archetype')->nullable()->after('is_homepage');
            $table->unique('archetype');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropUnique(['archetype']);
            $table->dropColumn('archetype');
        });
    }
};
