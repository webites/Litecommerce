<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ManageStockReservations::releaseExpiredForLockedItem() filters all three
        // columns under lockForUpdate(); without a covering index InnoDB locks every
        // pending reservation for the item (not just the expired ones), which caused
        // real cross-item deadlocks under load (see docs/knowledge-base/10-operations.md
        // "Testy obciążeniowe" — first occurrence at ~50-60 concurrent checkouts).
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->index(['stock_item_id', 'state', 'expires_at'], 'stock_reservations_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropIndex('stock_reservations_lookup_index');
        });
    }
};
