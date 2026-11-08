<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_message_reads', function (Blueprint $table): void {
            $table->unsignedBigInteger('staff_id');
            $table->string('message_key', 150);
            $table->timestamp('read_at');
            $table->primary(['staff_id', 'message_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_message_reads');
    }
};
