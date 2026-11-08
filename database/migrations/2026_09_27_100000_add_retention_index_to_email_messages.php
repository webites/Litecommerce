<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table): void {
            $table->index(['status', 'terminal_at'], 'email_messages_retention_idx');
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table): void {
            $table->dropIndex('email_messages_retention_idx');
        });
    }
};
