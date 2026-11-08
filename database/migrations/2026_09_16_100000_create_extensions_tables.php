<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primary key is the manifest's own stable id (e.g. "acme/invoices"),
        // not a surrogate — a plugin references itself and its dependencies
        // by this id everywhere (manifest, autoload registration, panel
        // routes), so there is no separate identity to reconcile.
        Schema::create('extensions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('version', 32);
            $table->string('type', 16)->default('plugin');
            $table->string('status', 16)->default('disabled');
            $table->json('manifest');
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        // Generic key-value settings for the common case a plugin declares
        // a handful of typed fields in its manifest instead of shipping its
        // own migration/table — see "Rozruch i rozszerzalność" (08-extensions.md).
        Schema::create('extension_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id');
            $table->string('key', 128);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['extension_id', 'key']);
            $table->foreign('extension_id')->references('id')->on('extensions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_settings');
        Schema::dropIfExists('extensions');
    }
};
