<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drives BOTH <meta name="robots" content="noindex"> on every page under
        // this language AND exclusion from /sitemap.xml — the two never make
        // sense to split (a sitemap should only ever list indexable URLs).
        Schema::table('languages', function (Blueprint $table): void {
            $table->boolean('noindex')->default(false)->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table): void {
            $table->dropColumn('noindex');
        });
    }
};
