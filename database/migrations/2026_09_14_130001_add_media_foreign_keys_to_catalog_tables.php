<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->foreign('logo_media_id')->references('id')->on('media')->nullOnDelete();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->foreign('image_media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropForeign(['logo_media_id']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeign(['image_media_id']);
        });
    }
};
