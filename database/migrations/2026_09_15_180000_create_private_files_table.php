<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Owned by Media\Contracts\PrivateFileLibrary — separate from
        // `media` (P1-003), which always lives on the publicly servable
        // disk. Digital product files must never share that disk.
        Schema::create('private_files', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('uploaded_by', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_files');
    }
};
