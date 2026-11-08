<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_drafts', function (Blueprint $table): void {
            // The shipping method key, with no FK: Delivery is a separate bounded context.
            $table->string('delivery_method_id', 191)->nullable()->after('shipping_address');
            $table->unsignedBigInteger('delivery_pickup_point_id')->nullable()->after('delivery_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_drafts', function (Blueprint $table): void {
            $table->dropColumn(['delivery_method_id', 'delivery_pickup_point_id']);
        });
    }
};
