<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class CartErrorLocalizationTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        foreach ([['pl', 'Polski', true, true], ['en', 'English', false, true], ['de', 'Deutsch', false, false]] as [$code, $name, $default, $enabled]) {
            DB::table('languages')->insert([
                'code' => $code, 'name' => $name, 'is_default' => $default, 'is_enabled' => $enabled,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('shop_currencies')->insert([
            'code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withCredentials();
    }

    public function test_rejection_message_follows_the_locale_header(): void
    {
        $this->putJson('/cart/currency', ['currency_code' => 'ZZZ'], ['X-Storefront-Locale' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'unknown_currency')
            ->assertJsonPath('message', 'Unknown currency.');
    }

    public function test_missing_header_falls_back_to_the_default_language(): void
    {
        $this->putJson('/cart/currency', ['currency_code' => 'ZZZ'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nieznana waluta.');
    }

    public function test_disabled_or_unknown_language_in_the_header_falls_back_to_the_default(): void
    {
        $this->putJson('/cart/currency', ['currency_code' => 'ZZZ'], ['X-Storefront-Locale' => 'de'])
            ->assertJsonPath('message', 'Nieznana waluta.');
        $this->putJson('/cart/currency', ['currency_code' => 'ZZZ'], ['X-Storefront-Locale' => 'xx'])
            ->assertJsonPath('message', 'Nieznana waluta.');
    }

    public function test_checkout_rejection_is_translated_too(): void
    {
        $this->postJson('/checkout', [], ['X-Storefront-Locale' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'empty_cart')
            ->assertJsonPath('message', 'The cart is empty.');
    }

    public function test_every_rejection_code_has_a_translation_in_both_languages(): void
    {
        $codes = [];
        foreach (glob(base_path('modules/Cart/Application/{*.php,Concerns/*.php}'), GLOB_BRACE) as $file) {
            preg_match_all("/new CartValidationException\\('([a-z_]+)'/", (string) file_get_contents($file), $matches);
            $codes = [...$codes, ...$matches[1]];
        }
        $codes = array_unique([...array_diff($codes, ['coupon_']), 'coupon_invalid_code', 'coupon_blocked', 'coupon_expired', 'coupon_account_required', 'coupon_account_mismatch', 'coupon_currency_unavailable', 'coupon_pin_required', 'coupon_pin_incorrect', 'coupon_pin_locked', 'coupon_not_combinable', 'coupon_module_disabled', 'price_changed']);

        foreach (['pl', 'en'] as $locale) {
            $translations = require base_path("modules/Cart/lang/{$locale}/errors.php");
            foreach ($codes as $code) {
                $this->assertArrayHasKey($code, $translations, "Missing '{$code}' in {$locale}");
            }
        }
    }
}
