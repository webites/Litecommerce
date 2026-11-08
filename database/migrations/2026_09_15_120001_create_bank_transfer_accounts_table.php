<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfer_accounts', function (Blueprint $table): void {
            $table->string('currency_code', 3)->primary();
            $table->string('beneficiary_name');
            $table->string('account_number');
            $table->json('instructions')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('shop_currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_accounts');
    }
};
