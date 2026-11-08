<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 32)->default('draft');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->boolean('is_homepage')->default(false);
            $table->timestamps();
            $table->index(['status', 'publish_at']);
            $table->index('is_homepage');
        });

        Schema::create('cms_page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('slug');
            $table->string('title');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('content');
            $table->timestamps();
            $table->unique(['page_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_translations');
        Schema::dropIfExists('cms_pages');
    }
};
