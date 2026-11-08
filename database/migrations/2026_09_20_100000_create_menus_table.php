<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            // Internal admin-facing name only, never shown to a customer or translated —
            // the same "not a merchant-facing string" treatment as a WordPress menu's own name.
            $table->string('name');
            // The theme/plugin-declared location this menu renders at (e.g. 'primary',
            // 'footer') — at most one menu per location. Nullable+unique alone enforces
            // that (MySQL allows unlimited NULLs but blocks a duplicate non-null value),
            // the same trick already used for cms_pages.archetype.
            $table->string('location_id', 64)->nullable()->unique();
            // The whole item tree as one JSON blob — same "replace wholesale" shape as
            // cms_page_translations.content (Puck's page-builder content), not a
            // normalized menu_items table: a menu is edited and saved as one unit in
            // the panel, so there is no reordering/nesting concern that a JSON array
            // of {type, label, ..., children: [...]} doesn't already solve for free.
            $table->json('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
