<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class AccessControlTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
        $this->installReferenceTheme();
    }

    /** @return list<LaravelRoute> */
    private function panelRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            static fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'panel'),
        ));
    }

    private function urlFor(LaravelRoute $route): string
    {
        return '/'.preg_replace_callback('/\{(\w+)\??\}/', static function (array $match) use ($route): string {
            $pattern = $route->wheres[$match[1]] ?? null;
            foreach (['1', '6f1c2d3e-4a5b-4c6d-8e7f-0a1b2c3d4e5f', 'PLN', 'pl', 'abc', 'a1'] as $candidate) {
                if ($pattern === null || preg_match('#^(?:'.$pattern.')$#', $candidate) === 1) {
                    return $candidate;
                }
            }

            return '1';
        }, $route->uri());
    }

    public function test_no_panel_route_answers_a_guest_with_content_or_a_server_error(): void
    {
        $this->assertNotEmpty($this->panelRoutes());

        foreach ($this->panelRoutes() as $route) {
            $method = $route->methods()[0];
            $status = $this->call($method, $this->urlFor($route))->getStatusCode();

            $this->assertContains($status, [301, 302, 401, 403, 404, 405], "{$method} {$route->uri()} answered a guest with {$status}");
        }
    }

    public function test_every_permission_guarded_panel_route_refuses_staff_whose_permission_is_denied(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $byPermission = [];
        foreach ($this->panelRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'staff.permission:')) {
                    $byPermission[substr($middleware, strlen('staff.permission:'))][] = $route;
                }
            }
        }
        $this->assertNotEmpty($byPermission);

        foreach ($byPermission as $permission => $routes) {
            $staff = StaffAccount::factory()->create(['permission_overrides' => array_fill_keys(explode(',', (string) $permission), false)]);
            foreach ($routes as $route) {
                $method = $route->methods()[0];
                $status = $this->actingAs($staff)->call($method, $this->urlFor($route))->getStatusCode();

                $this->assertContains($status, [403, 404], "{$method} {$route->uri()} must refuse staff without [{$permission}], got {$status}");
            }
        }
    }

    public function test_download_tokens_cannot_be_guessed_and_unknown_tokens_reveal_nothing(): void
    {
        foreach (['0', 'admin', str_repeat('a', 40), '1', str_repeat('A1', 20)] as $token) {
            $this->get("/downloads/{$token}")->assertNotFound();
        }
    }

    public function test_a_guest_order_link_needs_its_secret_token_and_dies_when_revoked(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createPhysicalVariant($staff, '10.00');
        $this->startNewShopper();
        [$response] = $this->checkoutVariant($variantId, '1');
        $orderId = (string) $response->assertOk()->json('order.id');
        $access = app(GuestOrderAccess::class);

        $this->get("/pl/orders/{$orderId}")->assertNotFound();
        $this->get('/pl/orders/'.str_repeat('a', 43))->assertNotFound();

        $token = $access->issue($orderId);
        $this->assertGreaterThanOrEqual(40, strlen($token));
        $this->get("/pl/orders/{$token}")->assertOk();
        $this->assertNull(DB::table('guest_order_access_tokens')->where('token_hash', $token)->first(), 'only the hash of a token is stored');

        $access->revokeForOrder($orderId);
        $this->get("/pl/orders/{$token}")->assertNotFound();
    }

    public function test_one_customer_cannot_open_another_customers_order_and_a_guest_cannot_open_either(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createPhysicalVariant($staff, '10.00');
        $owner = CustomerAccount::query()->create(['email' => 'owner@example.com', 'password' => 'irrelevant']);
        $other = CustomerAccount::query()->create(['email' => 'other@example.com', 'password' => 'irrelevant']);
        $this->startNewShopper();
        $this->actingAs($owner, 'customer');
        [$response] = $this->checkoutVariant($variantId, '1');
        $orderId = (string) $response->assertOk()->json('order.id');

        $this->get("/pl/account/orders/{$orderId}")->assertOk();

        $this->startNewShopper();
        $this->actingAs($other, 'customer');
        $this->get("/pl/account/orders/{$orderId}")->assertNotFound();

        $this->startNewShopper();
        $this->get("/pl/account/orders/{$orderId}")->assertRedirect();
    }

    public function test_the_thank_you_page_of_a_checkout_is_not_readable_from_another_session(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createPhysicalVariant($staff, '10.00');
        $this->startNewShopper();
        [, $draftId] = $this->checkoutVariant($variantId, '1');

        $this->startNewShopper();
        $this->get("/pl/checkout/{$draftId}/thank-you")->assertNotFound();
        $this->getJson("/checkout/{$draftId}")->assertNotFound();
    }

    public function test_hostile_query_strings_never_cause_errors_or_leak_data(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->createPhysicalVariant($staff, '10.00');
        $payloads = ["' OR 1=1 --", '1; DROP TABLE products', '%00', '\\', str_repeat('x', 5000), '<script>alert(1)</script>'];

        foreach ($payloads as $payload) {
            foreach (['q', 'sort', 'category', 'page', 'min_price', 'attributes'] as $parameter) {
                $status = $this->get('/pl/products?'.http_build_query([$parameter => $payload]))->getStatusCode();
                $this->assertContains($status, [200, 404, 422], "{$parameter}={$payload} produced {$status}");
            }
        }

        $this->assertSame(1, DB::table('products')->count(), 'the table survived');
        $this->get('/pl/products?'.http_build_query(['q' => '<script>alert(1)</script>']))->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_session_cookies_are_http_only_and_not_cross_site(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }

    public function test_environment_files_and_source_are_not_served_by_the_web_root(): void
    {
        foreach (['/.env', '/composer.json', '/storage/logs/laravel.log', '/artisan'] as $path) {
            $this->assertNotSame(200, $this->get($path)->getStatusCode(), $path);
        }
        $this->assertFileDoesNotExist(public_path('.env'));
    }
}
