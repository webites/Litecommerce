<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_type', 32);
            $table->string('source_id', 64);
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('mode', 8)->default('live');
            $table->string('locale', 12);
            $table->string('currency_code', 3);
            $table->string('status', 32);
            $table->string('payment_status', 24);
            $table->string('payment_method_id', 128);
            // Kind of the payment method at placement (online, bank_transfer, cash_on_delivery, pay_on_pickup, free).
            $table->string('payment_kind', 32);
            $table->decimal('total_net_amount', 20, 6);
            $table->decimal('total_vat_amount', 20, 6);
            $table->decimal('total_gross_amount', 20, 6);
            $table->timestamp('payment_deadline_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->uuid('paid_attempt_id')->nullable();
            $table->boolean('deadline_extended')->default(false);
            $table->timestamp('anonymized_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'payment_deadline_at']);
            $table->index(['customer_account_id', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index(['anonymized_at', 'created_at']);
        });

        Schema::create('order_party_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('buyer_type', 16);
            $table->string('email');
            $table->string('company_name')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('phone')->nullable();
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('stock_reservation_id')->nullable();
            $table->string('product_type', 32);
            $table->string('sku');
            $table->string('display_name');
            $table->string('unit_code', 32)->default('piece');
            $table->decimal('quantity', 20, 6);
            $table->decimal('unit_amount', 20, 6);
            $table->decimal('line_net_amount', 20, 6);
            $table->decimal('line_vat_amount', 20, 6);
            $table->decimal('line_gross_amount', 20, 6);
            $table->json('options_snapshot')->nullable();
            $table->json('allocations_snapshot')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'id']);
            $table->index('variant_id');
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->string('actor_type', 32);
            $table->string('actor_id', 64)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['order_id', 'occurred_at']);
        });

        Schema::create('order_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->text('body');
            $table->string('author_type', 32);
            $table->string('author_id', 64)->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_outbox_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('event_type', 128);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedInteger('aggregate_version');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('available_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['published_at', 'available_at']);
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider_id', 128);
            $table->string('method_id', 128);
            $table->string('provider_transaction_id')->nullable();
            $table->string('status', 24);
            $table->decimal('amount', 20, 6);
            $table->string('currency_code', 3);
            $table->string('mode', 8);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->unique(['provider_id', 'method_id', 'provider_transaction_id'], 'payment_transaction_idempotency');
        });

        Schema::create('payment_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->string('provider_id', 128);
            $table->string('external_notification_id')->nullable();
            $table->string('payload_hash', 64);
            $table->string('mapped_status', 24);
            $table->json('sanitized_payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['provider_id', 'payload_hash']);
            $table->index(['provider_id', 'external_notification_id']);
        });

        Schema::create('deferred_payment_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('method_id', 191);
            $table->longText('body');
            $table->longText('headers');
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error_code', 191)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'received_at']);
            $table->index('finished_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deferred_payment_webhooks');
        Schema::dropIfExists('payment_notifications');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('order_outbox_events');
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('order_party_snapshots');
        Schema::dropIfExists('orders');
    }
};
