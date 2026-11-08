<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['extensions', 'themes'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('package_sha256', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['extensions', 'themes'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('package_sha256');
            });
        }
    }
};
