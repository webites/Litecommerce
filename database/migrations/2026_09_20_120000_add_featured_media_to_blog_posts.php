<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('featured_media_id')
                ->nullable()
                ->after('status')
                ->constrained('media')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_media_id');
        });
    }
};
