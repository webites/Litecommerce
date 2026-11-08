<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/** HTTP-level coverage for /panel/orders/{order} not covered by OrderPaymentTest's heavier fixtures. */
final class OrderPanelTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_an_unknown_order_redirects_to_the_listing_with_an_error(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.orders.show', '00000000-0000-0000-0000-000000000000'))
            ->assertRedirect(route('panel.orders.index'))
            ->assertSessionHas('error');
    }
}
