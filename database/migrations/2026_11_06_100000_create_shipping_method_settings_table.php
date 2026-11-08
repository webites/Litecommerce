<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_method_settings', function (Blueprint $table): void {
            $table->string('method_id', 191)->primary();
            $table->boolean('active')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->boolean('allow_cash_on_delivery')->default(false);
            $table->boolean('allow_pay_on_pickup')->default(false);
            $table->unsignedSmallInteger('pickup_deadline_days')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_method_settings');
    }
};
