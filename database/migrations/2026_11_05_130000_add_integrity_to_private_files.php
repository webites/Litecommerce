<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('private_files', function (Blueprint $table): void {
            $table->string('integrity_status', 16)->default('ok')->after('checksum_sha256');
            $table->timestamp('verified_at')->nullable()->after('integrity_status');
            $table->index(['verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('private_files', function (Blueprint $table): void {
            $table->dropIndex(['verified_at']);
            $table->dropColumn(['integrity_status', 'verified_at']);
        });
    }
};
