<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->timestamps();
        });

        Schema::create('brand_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->longText('description_html')->nullable();
            $table->timestamps();
            $table->unique(['brand_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('visible_in_tree')->default(true);
            $table->unsignedBigInteger('image_media_id')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'position']);
        });

        Schema::create('category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->longText('description_html')->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('attribute_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 96)->unique();
            $table->string('type', 32);
            $table->boolean('is_filterable')->default(false);
            $table->timestamps();
        });

        Schema::create('attribute_definition_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('attribute_definition_id');
            $table->string('locale', 16);
            $table->string('name');
            $table->timestamps();
            $table->unique(['attribute_definition_id', 'locale'], 'attribute_definition_locale_unique');
            $table->foreign('attribute_definition_id', 'attribute_definition_translation_fk')
                ->references('id')->on('attribute_definitions')->cascadeOnDelete();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->string('value_key', 96);
            $table->char('color_hex', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_definition_id', 'value_key'], 'attribute_value_key_unique');
        });

        Schema::create('attribute_value_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->timestamps();
            $table->unique(['attribute_value_id', 'locale']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64);
            $table->string('status', 32)->default('draft');
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('featured')->default(false);
            $table->string('unit_code', 32)->default('piece');
            $table->decimal('minimum_quantity', 20, 6)->default(1);
            $table->decimal('maximum_quantity', 20, 6)->nullable();
            $table->decimal('quantity_step', 20, 6)->default(1);
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'publish_at']);
            $table->index(['brand_id', 'status']);
            $table->index(['primary_category_id', 'status'], 'products_primary_category_status_idx');
        });

        Schema::create('product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->longText('description_html')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 96)->unique();
            $table->string('gtin', 14)->nullable()->unique();
            $table->string('mpn', 191)->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->char('option_signature', 64)->nullable();
            $table->unsignedBigInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'option_signature'], 'variant_option_signature_unique');
            $table->index(['product_id', 'enabled', 'archived_at'], 'variants_product_availability_idx');
            $table->index(['mpn', 'product_id']);
        });

        Schema::create('variant_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description_html')->nullable();
            $table->timestamps();
            $table->unique(['product_variant_id', 'locale'], 'variant_translation_locale_unique');
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->decimal('number_value', 20, 6)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'attribute_definition_id'], 'product_attribute_definition_idx');
        });

        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'attribute_definition_id']);
        });

        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['product_option_id', 'attribute_value_id']);
        });

        Schema::create('variant_option_values', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_variant_id', 'product_option_id'], 'variant_option_value_primary');
            $table->index(['attribute_value_id', 'product_variant_id'], 'variant_attribute_value_idx');
        });

        Schema::create('variant_attribute_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->decimal('number_value', 20, 6)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['product_variant_id', 'attribute_definition_id'], 'variant_attribute_definition_idx');
        });

        Schema::create('product_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 32);
            $table->foreignId('target_product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('target_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['source_product_id', 'type', 'position'], 'product_relations_source_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('variant_attribute_overrides');
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('variant_translations');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_value_translations');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_definition_translations');
        Schema::dropIfExists('attribute_definitions');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brand_translations');
        Schema::dropIfExists('brands');
    }
};
