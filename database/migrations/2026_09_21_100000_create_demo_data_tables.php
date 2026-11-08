<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_data_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('status', 32);
            $table->string('active_key', 32)->nullable()->unique();
            $table->string('locale', 16);
            $table->string('currency_code', 3);
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_data_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('batch_id')->constrained('demo_data_batches')->cascadeOnDelete();
            $table->string('contributor', 64);
            $table->string('resource_type', 96);
            $table->string('resource_id', 191);
            $table->unsignedInteger('position');
            $table->timestamp('created_at');
            $table->unique(['batch_id', 'contributor', 'resource_type', 'resource_id'], 'demo_resource_identity_unique');
            $table->index(['batch_id', 'contributor', 'resource_type'], 'demo_resource_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_data_resources');
        Schema::dropIfExists('demo_data_batches');
    }
};
