<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('on_hand_quantity', 20, 6)->default(0);
            $table->decimal('reserved_quantity', 20, 6)->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->timestamps();
            $table->unique(['variant_id', 'warehouse_id']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->string('state', 16);
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['stock_item_id', 'state']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['state', 'expires_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('quantity_delta', 20, 6);
            $table->string('reason')->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['stock_item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_items');
    }
};
