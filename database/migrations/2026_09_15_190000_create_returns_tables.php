<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // The "zgłoszenie" — who asked and why, kept separate from the
        // goods (ReturnLine) and money (RefundRecord) sides so a rejected
        // request never implies stock or a payout
        // (docs/knowledge-base/06-fulfillment.md#zwroty).
        Schema::create('return_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('source', 16);
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->text('comment')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->index('order_id');
            $table->index(['anonymized_at', 'created_at']);
        });

        // The "pieniądze" side — one record per staff decision batch.
        // money_refund_amount is informational until paid_out_at is set by
        // a deliberate, separate action: "Akcja w panelu emituje event i
        // WYRAŹNIE mówi, że sama nie wysyła pieniędzy".
        Schema::create('refund_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('products_net_amount', 20, 6)->default(0);
            $table->decimal('products_vat_amount', 20, 6)->default(0);
            $table->decimal('products_gross_amount', 20, 6)->default(0);
            $table->decimal('adjustments_net_amount', 20, 6)->default(0);
            $table->decimal('adjustments_vat_amount', 20, 6)->default(0);
            $table->decimal('adjustments_gross_amount', 20, 6)->default(0);
            $table->decimal('coupon_restored_amount', 20, 6)->default(0);
            $table->decimal('delivery_net_amount', 20, 6)->nullable();
            $table->decimal('delivery_vat_amount', 20, 6)->nullable();
            $table->decimal('delivery_gross_amount', 20, 6)->nullable();
            $table->decimal('money_refund_amount', 20, 6);
            $table->string('decided_by', 64);
            $table->timestamp('decided_at');
            $table->timestamp('paid_out_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->string('payout_recorded_by', 64)->nullable();
            $table->timestamps();
            $table->index('order_id');
        });

        // The "ilość"/goods side. Money fields here are the per-line
        // breakdown feeding a RefundRecord — proportional to quantity,
        // per "Częściowy zwrot dzieli wyłącznie zapisane kwoty pozycji,
        // proporcjonalnie do zwracanej ilości" (04-pricing-coupons.md).
        Schema::create('return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained('order_lines')->cascadeOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->foreignId('reason_id')->nullable()->constrained('return_reasons')->nullOnDelete();
            $table->text('reason_comment')->nullable();
            $table->string('status', 16)->default('pending');
            $table->boolean('restock')->default(false);
            $table->decimal('restocked_quantity', 20, 6)->nullable();
            $table->decimal('net_amount', 20, 6)->nullable();
            $table->decimal('vat_amount', 20, 6)->nullable();
            $table->decimal('gross_amount', 20, 6)->nullable();
            $table->json('coupon_allocations')->nullable();
            $table->json('adjustment_allocations')->nullable();
            $table->decimal('money_refund_amount', 20, 6)->nullable();
            $table->foreignId('refund_record_id')->nullable()->constrained('refund_records')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->string('decided_by', 64)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index('return_request_id');
            $table->index('order_line_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_lines');
        Schema::dropIfExists('refund_records');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('return_reasons');
    }
};
