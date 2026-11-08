<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_checks', function (Blueprint $table) {
            $table->id();
            $table->string('step_key')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_checks');
    }
};
