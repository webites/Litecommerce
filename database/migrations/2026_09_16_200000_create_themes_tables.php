<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same "primary key is the manifest's own id" reasoning as
        // extensions — see database/migrations/2026_09_16_100000_create_extensions_tables.php.
        Schema::create('themes', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('version', 32);
            $table->string('engine', 16)->default('blade');
            $table->string('status', 16)->default('installed');
            $table->json('manifest');
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('theme_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('theme_id');
            $table->string('key', 128);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['theme_id', 'key']);
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
        Schema::dropIfExists('themes');
    }
};
