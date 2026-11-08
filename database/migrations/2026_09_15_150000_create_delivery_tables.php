<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            // Meaningful for kind=pickup only — a flat_rate method is
            // priced exclusively through its delivery_region_rules.
            $table->decimal('price_amount', 20, 6)->nullable();
            $table->decimal('free_threshold_amount', 20, 6)->nullable();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->boolean('supports_cash_on_delivery')->default(false);
            // Meaningful for kind=pickup only — "płatność na miejscu ...
            // wymaga odbioru osobistego" (06-fulfillment.md).
            $table->boolean('supports_pay_on_pickup')->default(false);
            // Days after "ready for pickup" before an unpaid pay-on-pickup order lapses; null = no deadline.
            $table->unsignedSmallInteger('pickup_deadline_days')->nullable();
            $table->timestamps();
            $table->index(['kind', 'active']);
        });

        Schema::create('delivery_method_currency_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_method_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('price_amount', 20, 6)->nullable();
            $table->decimal('free_threshold_amount', 20, 6)->nullable();
            $table->timestamps();
            $table->unique(['delivery_method_id', 'currency_code'], 'delivery_method_currency_unique');
            $table->foreign('currency_code')->references('code')->on('shop_currencies')->restrictOnDelete();
        });

        Schema::create('delivery_region_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_method_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 16); // exclude | country_rate | continent_rate
            $table->string('country_code', 2)->nullable();
            $table->string('continent_code', 2)->nullable();
            // Null for "exclude" rules; required for the two rate types.
            $table->decimal('price_amount', 20, 6)->nullable();
            $table->timestamps();
            $table->index(['delivery_method_id', 'rule_type']);
        });

        Schema::create('delivery_region_rule_currency_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_region_rule_id')->constrained(indexName: 'delivery_region_rule_currency_overrides_rule_id_foreign')->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('price_amount', 20, 6);
            $table->timestamps();
            $table->unique(['delivery_region_rule_id', 'currency_code'], 'delivery_region_rule_currency_unique');
            $table->foreign('currency_code')->references('code')->on('shop_currencies')->restrictOnDelete();
        });

        Schema::create('delivery_pickup_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_method_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('country_code', 2);
            $table->string('postal_code', 20);
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->json('instructions')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['delivery_method_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_pickup_points');
        Schema::dropIfExists('delivery_region_rule_currency_overrides');
        Schema::dropIfExists('delivery_region_rules');
        Schema::dropIfExists('delivery_method_currency_overrides');
        Schema::dropIfExists('delivery_methods');
    }
};
