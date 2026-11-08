<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->string('original_filename', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->string('alt_text', 255)->nullable();
            $table->string('uploaded_by', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('media_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('owner_type', 64);
            $table->unsignedBigInteger('owner_id');
            $table->string('collection', 64)->default('gallery');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['media_id', 'owner_type', 'owner_id', 'collection'], 'media_attachment_unique');
            $table->index(['owner_type', 'owner_id', 'collection', 'position'], 'media_attachment_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
        Schema::dropIfExists('media');
    }
};
