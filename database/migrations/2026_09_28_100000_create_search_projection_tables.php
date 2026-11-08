<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('active_generation')->nullable();
            $table->boolean('dirty')->default(true);
            $table->timestamp('rebuilding_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });
        DB::table('search_index_states')->insert(['id' => 1, 'dirty' => true, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('generation');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->string('locale', 16);
            $table->string('slug');
            $table->string('product_type', 64);
            $table->string('product_name');
            $table->string('variant_name')->default('');
            $table->string('sku', 96);
            $table->string('variant_path')->default('');
            $table->string('exact_name', 512);
            $table->text('name_terms');
            $table->text('attribute_terms');
            $table->text('category_terms');
            $table->longText('description_terms');
            $table->unique(['generation', 'variant_id', 'locale'], 'search_document_generation_variant_locale_unique');
            $table->index(['generation', 'locale', 'product_id'], 'search_document_listing_idx');
            $table->index(['generation', 'locale', 'sku'], 'search_document_sku_idx');
        });

        Schema::create('search_document_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('search_document_id')->constrained('search_documents')->cascadeOnDelete();
            $table->string('definition_key', 96);
            $table->string('definition_name');
            $table->string('value_key', 191);
            $table->string('value_name');
            $table->boolean('filterable');
            $table->index(['definition_key', 'value_key', 'search_document_id'], 'search_attribute_filter_idx');
            $table->index(['search_document_id', 'filterable'], 'search_attribute_document_idx');
        });

        Schema::create('search_document_categories', function (Blueprint $table): void {
            $table->foreignId('search_document_id')->constrained('search_documents')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->primary(['search_document_id', 'category_id'], 'search_document_category_primary');
            $table->index(['category_id', 'search_document_id'], 'search_category_filter_idx');
        });

        Schema::create('search_variant_prices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('generation');
            $table->unsignedBigInteger('variant_id');
            $table->string('currency_code', 3);
            $table->decimal('effective_amount', 20, 6);
            $table->decimal('regular_amount', 20, 6);
            $table->boolean('promotional_active');
            $table->unique(['generation', 'variant_id', 'currency_code'], 'search_price_generation_variant_currency_unique');
            $table->index(['generation', 'currency_code', 'effective_amount', 'variant_id'], 'search_price_filter_idx');
        });

        Schema::create('search_variant_availability', function (Blueprint $table): void {
            $table->id();
            $table->uuid('generation');
            $table->unsignedBigInteger('variant_id');
            $table->boolean('available');
            $table->unique(['generation', 'variant_id'], 'search_availability_generation_variant_unique');
            $table->index(['generation', 'available', 'variant_id'], 'search_availability_filter_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_variant_availability');
        Schema::dropIfExists('search_variant_prices');
        Schema::dropIfExists('search_document_categories');
        Schema::dropIfExists('search_document_attributes');
        Schema::dropIfExists('search_documents');
        Schema::dropIfExists('search_index_states');
    }
};
