<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Litecommerce\Modules\Identity\Contracts\BuyerProfileType;
use Litecommerce\Modules\Identity\Contracts\CustomerPurchaseBlockChecker;
use Litecommerce\Modules\Identity\Infrastructure\Models\AccountPurchaseBlock;
use Litecommerce\Modules\Identity\Infrastructure\Models\BuyerProfile;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Tests\TestCase;

final class CustomerIdentityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_login_account_can_own_separate_person_and_company_buyer_profiles(): void
    {
        $account = CustomerAccount::query()->create([
            'email' => 'BUYER@EXAMPLE.COM',
            'password' => 'secret-password',
        ]);

        BuyerProfile::query()->create([
            'customer_account_id' => $account->id,
            'type' => BuyerProfileType::Person,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'is_default' => true,
        ]);
        BuyerProfile::query()->create([
            'customer_account_id' => $account->id,
            'type' => BuyerProfileType::Company,
            'company_name' => 'Przykład sp. z o.o.',
            'tax_identifier' => '1234567890',
        ]);

        $this->assertSame('buyer@example.com', $account->email);
        $this->assertCount(2, $account->buyerProfiles);
        $this->assertTrue($account->buyerProfiles->contains(
            fn (BuyerProfile $profile): bool => $profile->type === BuyerProfileType::Person,
        ));
    }

    public function test_active_block_prevents_customer_and_matching_guest_purchase_without_leaking_reason_to_guest(): void
    {
        $account = CustomerAccount::query()->create([
            'email' => 'buyer@example.com',
            'password' => 'secret-password',
        ]);
        AccountPurchaseBlock::query()->create([
            'customer_account_id' => $account->id,
            'reason' => 'Ręczna blokada sprzedawcy',
            'blocked_at' => now(),
        ]);

        $checker = app(CustomerPurchaseBlockChecker::class);

        $status = $checker->forCustomerAccount($account->id);
        $this->assertTrue($status->blocked);
        $this->assertSame('Ręczna blokada sprzedawcy', $status->reason);
        $this->assertTrue($checker->isGuestEmailBlocked(' BUYER@example.com '));

        AccountPurchaseBlock::query()->where('customer_account_id', $account->id)->update(['lifted_at' => now()]);

        $this->assertFalse($checker->forCustomerAccount($account->id)->blocked);
        $this->assertFalse($checker->isGuestEmailBlocked('buyer@example.com'));
    }
}
