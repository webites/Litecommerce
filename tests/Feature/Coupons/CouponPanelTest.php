<?php

declare(strict_types=1);

namespace Tests\Feature\Coupons;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/** HTTP-level coverage for /panel/coupons: CRUD, top-up, PIN reset, module settings, permission gate. */
final class CouponPanelTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('shop_currencies')->insert([
            'code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_an_unknown_coupon_redirects_to_the_listing_with_an_error(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.coupons.show', 999999))
            ->assertRedirect(route('panel.coupons.index'))
            ->assertSessionHas('error');
    }

    public function test_administrator_can_create_a_coupon_with_initial_balance(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.coupons.store'), [
            'code' => 'WELCOME10', 'combinable_with_promotional' => true,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
            'initial_balance' => '50.00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['code' => 'WELCOME10', 'balance' => '50.000000']);
        $this->assertDatabaseHas('coupon_ledger_entries', ['type' => 'issue', 'amount' => '50.000000']);
    }

    public function test_blank_code_generates_a_random_unique_one(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.coupons.store'), [
            'code' => '', 'combinable_with_promotional' => true,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
        ])->assertSessionHasNoErrors();

        $code = DB::table('coupons')->value('code');
        $this->assertNotEmpty($code);
    }

    public function test_administrator_can_edit_block_and_top_up_a_coupon(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.coupons.store'), [
            'code' => 'EDITME', 'combinable_with_promotional' => true,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => false, 'initial_balance' => '10.00',
        ]);
        $couponId = (int) DB::table('coupons')->value('id');

        $this->actingAs($staff)->put(route('panel.coupons.update', $couponId), [
            'status' => 'blocked', 'combinable_with_promotional' => false,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', ['id' => $couponId, 'status' => 'blocked', 'applies_to_delivery' => true]);

        $this->actingAs($staff)->post(route('panel.coupons.top-up', $couponId), ['amount' => '5.00'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', ['id' => $couponId, 'balance' => '15.000000']);
    }

    public function test_pin_attempts_reset_by_staff(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.coupons.store'), [
            'code' => 'PINNED', 'pin' => '1234', 'combinable_with_promotional' => true,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
        ]);
        $couponId = (int) DB::table('coupons')->value('id');
        DB::table('coupons')->where('id', $couponId)->update(['failed_pin_attempts' => 5, 'pin_locked_at' => now()]);

        $this->actingAs($staff)->post(route('panel.coupons.reset-pin', $couponId))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['id' => $couponId, 'failed_pin_attempts' => 0, 'pin_locked_at' => null]);
    }

    public function test_administrator_can_toggle_module_settings(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.coupons.settings.update'), ['enabled' => false])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupon_settings', ['id' => 1, 'enabled' => false]);
    }

    public function test_staff_without_manage_coupons_permission_is_forbidden(): void
    {
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->get(route('panel.coupons.index'))->assertForbidden();
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.coupons.store'), [
            'code' => 'DUPE', 'combinable_with_promotional' => true,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
        ]);

        $this->actingAs($staff)->post(route('panel.coupons.store'), [
            'code' => 'DUPE', 'combinable_with_promotional' => true,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
        ])->assertSessionHasErrors('code');
    }
}
