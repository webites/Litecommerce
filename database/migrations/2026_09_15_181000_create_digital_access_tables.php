<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Kilka plików na wariant, różne zestawy wariantów" — each row is
        // one downloadable file slot belonging to one digital variant.
        // Limit/validity are configured here and snapshotted onto every
        // grant created from this slot, so a later edit never reaches back
        // into rights already sold (docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy).
        Schema::create('variant_file_sets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->string('label');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('validity_days')->nullable();
            $table->unsignedInteger('position')->default(0);
            // FK to digital_asset_versions added below, once that table exists.
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->index(['variant_id', 'position']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        // "Zakup utrwala wersje plików z chwili zakupu. Zmiana pliku nie
        // zmienia wcześniejszych praw." — re-uploading a file creates a new
        // version row rather than overwriting private_files; existing
        // grants keep pointing at the version they were created against.
        Schema::create('digital_asset_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_file_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('private_file_id')->constrained('private_files')->restrictOnDelete();
            // Snapshot from PrivateFileData at upload time — lets grant
            // listings render a filename/size without a cross-module call
            // into Media for every row.
            $table->string('original_filename');
            $table->unsignedBigInteger('size_bytes');
            $table->string('uploaded_by', 64)->nullable();
            $table->timestamps();
            $table->unique(['variant_file_set_id', 'version_number']);
        });

        Schema::table('variant_file_sets', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('digital_asset_versions')->restrictOnDelete();
        });

        // One grant per (purchased line × file). reference_type/reference_id
        // mirrors Warehouse\Contracts\StockReservationManager::reserve() —
        // Digital does not depend on Orders' models, only on scalars Orders
        // passes in. order_id/customer_account_id/email are a display
        // convenience for the panel and are not used for authorization —
        // the access_token is (see docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy,
        // "linki nie są publicznymi adresami plików").
        Schema::create('download_grants', function (Blueprint $table): void {
            $table->id();
            $table->string('access_token', 64)->unique();
            $table->foreignId('variant_file_set_id')->constrained()->restrictOnDelete();
            $table->foreignId('digital_asset_version_id')->constrained()->restrictOnDelete();
            $table->string('reference_type', 64);
            $table->string('reference_id', 64);
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('downloads_used')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
            $table->unique(['reference_type', 'reference_id', 'variant_file_set_id'], 'download_grants_reference_file_unique');
            $table->index('order_id');
            $table->index(['granted_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // One row per HTTP request against a grant — audit trail and the
        // basis for the resumed-download rule documented alongside
        // ConsumeDownload: a Range request continuing a prior attempt does
        // not consume another unit of the limit.
        Schema::create('download_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('download_grant_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedBigInteger('range_start')->nullable();
            $table->unsignedBigInteger('range_end')->nullable();
            $table->boolean('counted')->default(true);
            $table->string('outcome', 16);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['download_grant_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_attempts');
        Schema::dropIfExists('download_grants');
        Schema::table('variant_file_sets', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('digital_asset_versions');
        Schema::dropIfExists('variant_file_sets');
    }
};
