<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 32)->default('draft');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'publish_at']);
        });

        Schema::create('blog_post_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('slug');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content_html');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->unique(['post_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('blog_categories')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('blog_category_post', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('blog_categories')->cascadeOnDelete();
            $table->primary(['post_id', 'category_id']);
            $table->index(['category_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_category_post');
        Schema::dropIfExists('blog_category_translations');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('blog_post_translations');
        Schema::dropIfExists('blog_posts');
    }
};
