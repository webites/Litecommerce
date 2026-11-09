<?php

declare(strict_types=1);

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Contracts\SetupChecklist;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class SetupChecklistTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_fresh_shop_lists_every_core_step_as_not_done(): void
    {
        $this->actingAs(StaffAccount::factory()->create());

        $steps = collect(app(SetupChecklist::class)->steps())->keyBy('key');

        $this->assertSame(['catalog', 'delivery', 'payments'], $steps->keys()->all());
        $this->assertFalse($steps->contains(static fn ($step): bool => $step->done));
    }

    public function test_a_pickup_method_without_a_point_does_not_complete_the_delivery_step(): void
    {
        $this->actingAs(StaffAccount::factory()->create());
        $methodId = DB::table('delivery_methods')->insertGetId(['kind' => 'pickup', 'name' => 'Odbiór', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertFalse($this->step('delivery')->done);

        DB::table('delivery_pickup_points')->insert(['delivery_method_id' => $methodId, 'name' => 'Sklep', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertTrue($this->step('delivery')->done);
    }

    public function test_steps_are_hidden_from_staff_who_cannot_act_on_them(): void
    {
        $this->actingAs(StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create());

        $keys = array_map(static fn ($step): string => $step->key, app(SetupChecklist::class)->steps());

        $this->assertNotContains('delivery', $keys);
        $this->assertNotContains('payments', $keys);
    }

    public function test_the_dashboard_receives_the_steps(): void
    {
        $this->actingAs(StaffAccount::factory()->create())->get('/panel')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Dashboard')->has('setup_steps', 3));
    }

    private function step(string $key): object
    {
        return collect(app(SetupChecklist::class)->steps())->firstWhere('key', $key);
    }
}
