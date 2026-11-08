<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedInteger('width')->nullable()->after('size_bytes');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->json('variants')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['width', 'height', 'variants']);
        });
    }
};
