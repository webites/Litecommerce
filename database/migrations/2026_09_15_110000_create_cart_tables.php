<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_token', 36)->nullable()->unique();
            $table->string('currency_code', 3);
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('last_activity_at');
            $table->timestamps();
            $table->index(['customer_account_id', 'status']);
            $table->index('last_activity_at');
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->decimal('quantity', 20, 6);
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        Schema::create('checkout_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cart_version_seen');
            $table->string('status', 16)->default('open');
            $table->string('buyer_type', 16);
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->string('currency_code', 3);
            $table->json('billing_address')->nullable();
            $table->boolean('ships_to_different_address')->default(false);
            $table->json('shipping_address')->nullable();
            $table->json('price_snapshot')->nullable();
            $table->string('price_hash', 64)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->index(['cart_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_drafts');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
