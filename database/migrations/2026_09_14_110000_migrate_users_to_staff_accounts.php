<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('users', 'staff_accounts');

        Schema::table('staff_accounts', function (Blueprint $table): void {
            $table->string('role', 64)->default('administrator')->after('password');
            $table->json('permission_overrides')->nullable()->after('role');
            $table->unsignedInteger('permission_revision')->default(0)->after('permission_overrides');
            $table->text('two_factor_secret')->nullable()->after('permission_revision');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('staff_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'role',
                'permission_overrides',
                'permission_revision',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });

        Schema::rename('staff_accounts', 'users');
    }
};
