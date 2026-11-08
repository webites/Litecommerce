<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_accounts', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('permission_revision');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->index(['is_active', 'role'], 'staff_active_role_idx');
        });
    }

    public function down(): void
    {
        Schema::table('staff_accounts', function (Blueprint $table): void {
            $table->dropIndex('staff_active_role_idx');
            $table->dropColumn(['is_active', 'deactivated_at']);
        });
    }
};
