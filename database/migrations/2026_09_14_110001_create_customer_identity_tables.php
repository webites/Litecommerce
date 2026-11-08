<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('buyer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('tax_identifier', 64)->nullable();
            $table->string('registration_number', 64)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['customer_account_id', 'is_default'], 'buyer_profiles_default_idx');
        });

        Schema::create('address_book_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('company_name')->nullable();
            $table->char('country_code', 2);
            $table->string('postal_code', 32);
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('phone', 64)->nullable();
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->timestamps();
            $table->index(['customer_account_id', 'is_default_billing'], 'addresses_default_billing_idx');
            $table->index(['customer_account_id', 'is_default_shipping'], 'addresses_default_shipping_idx');
        });

        Schema::create('account_purchase_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('blocked_at');
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('created_by_staff_account_id')->nullable()->constrained('staff_accounts')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_account_id', 'lifted_at', 'blocked_at'], 'purchase_blocks_active_idx');
        });

        Schema::create('customer_account_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_deletion_requests');
        Schema::dropIfExists('account_purchase_blocks');
        Schema::dropIfExists('address_book_entries');
        Schema::dropIfExists('buyer_profiles');
        Schema::dropIfExists('customer_accounts');
    }
};
