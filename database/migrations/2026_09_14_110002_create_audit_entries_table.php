<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 64);
            $table->string('actor_id', 64)->nullable();
            $table->string('action', 191);
            $table->string('subject_type', 191);
            $table->string('subject_id', 64)->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
