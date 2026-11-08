<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            // The shipping method key, with no FK: a configuration or plugin
            // may later be edited or removed, so the order snapshots all it needs.
            $table->string('delivery_method_id', 191)->nullable();
            $table->string('kind', 16)->nullable();
            $table->string('name')->nullable();
            $table->decimal('net_amount', 20, 6)->nullable();
            $table->decimal('vat_amount', 20, 6)->nullable();
            $table->decimal('gross_amount', 20, 6)->nullable();
            $table->unsignedBigInteger('pickup_point_id')->nullable();
            $table->json('pickup_point_snapshot')->nullable();
            // Fulfillment fact of the delivery, independent of the order status.
            $table->string('fulfillment_state', 24)->default('pending');
            $table->timestamp('ready_for_pickup_at')->nullable();
            $table->timestamp('pickup_deadline_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('pickup_reminder_at')->nullable();
            $table->timestamp('pickup_reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['fulfillment_state', 'pickup_deadline_at']);
            $table->index(['fulfillment_state', 'pickup_reminder_at']);
        });

        Schema::create('order_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_delivery_id')->constrained('order_deliveries')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamp('occurred_at');
            $table->string('actor_type', 16);
            $table->string('actor_id', 64)->nullable();
            $table->json('data')->nullable();
            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_delivery_events');
        Schema::dropIfExists('order_deliveries');
    }
};
