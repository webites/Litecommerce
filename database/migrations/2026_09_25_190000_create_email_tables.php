<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_layouts', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100);
            $table->string('locale', 10);
            $table->string('name');
            $table->longText('html');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['key', 'locale']);
            $table->index(['locale', 'is_default']);
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('definition_key', 120);
            $table->string('locale', 10);
            $table->boolean('enabled')->nullable();
            $table->text('subject')->nullable();
            $table->longText('html')->nullable();
            $table->foreignId('layout_id')->nullable()->constrained('email_layouts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['definition_key', 'locale']);
        });

        Schema::create('email_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('definition_key', 120);
            $table->string('source_type', 80)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->string('actor_id', 191)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->string('mode', 10)->default('live');
            $table->unsignedTinyInteger('priority')->default(50);
            $table->string('locale', 10);
            $table->text('to_email');
            $table->text('to_name')->nullable();
            $table->char('recipient_hash', 64)->index();
            $table->longText('variables');
            $table->longText('template_subject');
            $table->longText('template_html');
            $table->longText('layout_html');
            $table->longText('rendered_subject')->nullable();
            $table->longText('rendered_html')->nullable();
            $table->longText('rendered_text')->nullable();
            $table->string('transport_key', 80)->default('smtp');
            $table->string('transport_owner', 100)->nullable()->index();
            $table->string('status', 32)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('leased_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('terminal_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->string('last_error_message', 1000)->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at', 'priority'], 'email_messages_due_idx');
            $table->index(['source_type', 'source_id'], 'email_messages_source_idx');
        });

        Schema::create('email_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('email_message_id');
            $table->string('disk', 80);
            $table->string('path', 500);
            $table->string('filename');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamps();
            $table->foreign('email_message_id')->references('id')->on('email_messages')->cascadeOnDelete();
        });

        Schema::create('email_send_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('email_message_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->string('transport_key', 80);
            $table->string('outcome', 32);
            $table->string('error_code', 120)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->foreign('email_message_id')->references('id')->on('email_messages')->cascadeOnDelete();
            $table->unique(['email_message_id', 'attempt_number'], 'email_attempt_number_unique');
        });

        Schema::create('guest_order_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->uuid('order_id')->index();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::create('customer_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::dropIfExists('guest_order_access_tokens');
        Schema::dropIfExists('email_send_attempts');
        Schema::dropIfExists('email_message_attachments');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_layouts');
    }
};
