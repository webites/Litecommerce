<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Amount-based balance lives in the shop's base currency only —
        // "Kwota i saldo w bazowej walucie" (04-pricing-coupons.md).
        // balance/reserved_amount are denormalized running totals kept in
        // step with coupon_ledger_entries/coupon_reservations in the same
        // transaction, mirroring stock_items.{on_hand,reserved}_quantity.
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('status', 16)->default('active');
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pin_hash')->nullable();
            $table->unsignedInteger('failed_pin_attempts')->default(0);
            $table->timestamp('pin_locked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('combinable_with_promotional')->default(true);
            $table->boolean('combinable_with_other_coupons')->default(true);
            $table->boolean('applies_to_delivery')->default(false);
            $table->decimal('balance', 20, 6)->default(0);
            $table->decimal('reserved_amount', 20, 6)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        // Immutable history — issue/topup, usage, restore (compensating
        // credit) and manual adjustment. balance is the sum of this table;
        // see "Projekt modeli salda" in 04-pricing-coupons.md.
        Schema::create('coupon_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 20, 6);
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->text('reason')->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['coupon_id', 'occurred_at']);
        });

        // Same reservation shape as stock_reservations — pending (held
        // during online payment's window) -> confirmed (permanently
        // consumed, ledger 'usage' already written) -> released/expired.
        Schema::create('coupon_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 20, 6);
            $table->string('state', 16);
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['coupon_id', 'state']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['state', 'expires_at']);
        });

        // Merchant-facing on/off switch (separate from config/modules.php's
        // developer/deploy-time provider toggle): disables only new code
        // attachment/reservation, never existing reservations or returns —
        // "Wyłączenie modułu zatrzymuje nowe użycia, ale istniejące
        // rezerwacje/zwroty nadal obsługiwane" (04-pricing-coupons.md).
        Schema::create('coupon_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        DB::table('coupon_settings')->insert(['id' => 1, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_settings');
        Schema::dropIfExists('coupon_reservations');
        Schema::dropIfExists('coupon_ledger_entries');
        Schema::dropIfExists('coupons');
    }
};
