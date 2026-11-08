<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('price_entry_mode', 8)->default('gross');
            $table->timestamps();
        });
        DB::table('pricing_settings')->insert(['id' => 1, 'price_entry_mode' => 'gross', 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('kind', 16);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('product_tax_assignments', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->primary();
            $table->foreignId('tax_rate_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('currency_code', 3)->unique();
            $table->decimal('base_units_per_unit', 20, 6);
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('shop_currencies')->restrictOnDelete();
        });

        Schema::create('variant_prices', function (Blueprint $table): void {
            $table->unsignedBigInteger('variant_id')->primary();
            $table->decimal('regular_amount', 20, 6);
            $table->string('regular_mode', 8);
            $table->decimal('promotional_amount', 20, 6)->nullable();
            $table->string('promotional_mode', 8)->nullable();
            $table->timestamps();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        Schema::create('variant_price_overrides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->string('currency_code', 3);
            $table->decimal('regular_amount', 20, 6)->nullable();
            $table->string('regular_mode', 8)->nullable();
            $table->decimal('promotional_amount', 20, 6)->nullable();
            $table->string('promotional_mode', 8)->nullable();
            $table->timestamps();
            $table->unique(['variant_id', 'currency_code']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('currency_code')->references('code')->on('shop_currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_price_overrides');
        Schema::dropIfExists('variant_prices');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('product_tax_assignments');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('pricing_settings');
    }
};
