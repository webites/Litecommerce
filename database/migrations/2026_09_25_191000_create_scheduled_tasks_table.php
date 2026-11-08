<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table): void {
            $table->string('key', 191)->primary();
            $table->unsignedInteger('interval_seconds');
            $table->string('status', 20)->default('idle');
            $table->timestamp('next_run_at')->index();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error_code', 191)->nullable();
            $table->unsignedBigInteger('run_count')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
            $table->index(['status', 'next_run_at'], 'scheduled_tasks_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};
