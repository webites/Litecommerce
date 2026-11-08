<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_consents', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('status', 16);
            $table->char('unsubscribe_token', 40)->unique();
            $table->string('locale', 10);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->index(['status', 'withdrawn_at']);
        });

        Schema::create('marketing_consent_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_consent_id')->constrained()->cascadeOnDelete();
            $table->string('action', 16);
            $table->string('source', 32);
            $table->string('locale', 10);
            $table->char('content_version', 12)->nullable();
            $table->text('content_text')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at');
            $table->index(['marketing_consent_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_consent_events');
        Schema::dropIfExists('marketing_consents');
    }
};
