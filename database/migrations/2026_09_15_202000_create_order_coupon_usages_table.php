<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per distinct code used on an order — the reservation is
        // order-level (a single code can fund both product lines and
        // delivery), unlike stock_reservation_id which is per OrderLine.
        // No FK to coupons/coupon_reservations — same cross-module
        // convention as order_lines.variant_id before Catalog, or
        // order_deliveries.delivery_method_id before Delivery: this table
        // is Orders' own snapshot bookkeeping, resolvable purely by the
        // reservation id it stores.
        Schema::create('order_coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('coupon_code', 64);
            $table->unsignedBigInteger('coupon_reservation_id')->nullable();
            $table->decimal('product_gross_amount', 20, 6)->default(0);
            $table->decimal('delivery_gross_amount', 20, 6)->default(0);
            $table->timestamps();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_coupon_usages');
    }
};
