<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No FK to coupons — Coupons is a separate bounded context,
        // referenced only by its code (same convention as
        // checkout_drafts.delivery_method_id towards Delivery). A code
        // that no longer resolves is simply reported as unavailable by
        // Coupons\Contracts\CouponPricer at pricing time, not a foreign
        // key violation here.
        Schema::create('cart_coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->timestamp('attached_at');
            $table->timestamps();
            $table->unique(['cart_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_coupons');
    }
};
