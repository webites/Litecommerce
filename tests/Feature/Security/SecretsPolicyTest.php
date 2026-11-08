<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Support\Logging\RedactSecrets;
use Illuminate\Encryption\Encrypter;
use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Extensions\Contracts\ExtensionSettingsStore;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class SecretsPolicyTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_log_records_never_contain_secret_values(): void
    {
        $handler = new TestHandler;
        $logger = new Logger('test', [$handler]);
        (new RedactSecrets)(new IlluminateLogger($logger));

        $logger->error('Call with Bearer abcdefghij1234567890 failed at https://x.test/cb?token=SUPERSECRET&a=1', [
            'order_id' => 'o-1',
            'smtp_password' => 'hunter2',
            'nested' => ['api_key' => 'sk_live_123', 'safe' => 'visible'],
            'Authorization' => 'Basic dXNlcjpwYXNz',
        ]);

        $record = $handler->getRecords()[0];
        $dump = json_encode([$record->message, $record->context]);
        foreach (['hunter2', 'sk_live_123', 'abcdefghij1234567890', 'SUPERSECRET', 'dXNlcjpwYXNz'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $dump);
        }
        $this->assertSame('o-1', $record->context['order_id']);
        $this->assertSame('visible', $record->context['nested']['safe']);
        $this->assertTrue($handler->hasRecordThatContains('[redacted]', Level::Error));
    }

    public function test_the_configured_channel_builds_with_the_tap_and_writes_redacted_records(): void
    {
        $file = sys_get_temp_dir().'/litecommerce-log-'.uniqid().'.log';
        config(['logging.channels.single.path' => $file]);
        Log::forgetChannel('single');

        Log::channel('single')->error('Gateway failed', ['password' => 'hunter2', 'order_id' => 'o-1']);

        $written = (string) file_get_contents($file);
        @unlink($file);
        $this->assertStringContainsString('Gateway failed', $written);
        $this->assertStringContainsString('[redacted]', $written);
        $this->assertStringNotContainsString('hunter2', $written);
        $this->assertStringNotContainsString('Unable to create configured logger', $written);
    }

    public function test_every_logging_channel_that_writes_somewhere_redacts_secrets(): void
    {
        foreach (['single', 'daily', 'monthly', 'stderr', 'errorlog', 'syslog'] as $channel) {
            $this->assertContains(RedactSecrets::class, config("logging.channels.{$channel}.tap"), $channel);
        }
    }

    public function test_reencryption_moves_every_stored_secret_to_the_new_key_and_flags_undecryptable_ones(): void
    {
        $oldKey = config('app.key');
        $staff = StaffAccount::factory()->create();
        DB::table('staff_accounts')->where('id', $staff->id)->update(['two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP')]);
        $oldCipher = (string) DB::table('staff_accounts')->where('id', $staff->id)->value('two_factor_secret');

        $newKey = 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));
        config(['app.key' => $newKey, 'app.previous_keys' => [$oldKey]]);
        $this->app->forgetInstance('encrypter');
        $this->app->forgetInstance(\Illuminate\Contracts\Encryption\Encrypter::class);
        Crypt::clearResolvedInstance('encrypter');

        $this->artisan('secrets:reencrypt')->assertSuccessful();

        $newCipher = (string) DB::table('staff_accounts')->where('id', $staff->id)->value('two_factor_secret');
        $this->assertNotSame($oldCipher, $newCipher);
        $this->assertSame('JBSWY3DPEHPK3PXP', (new Encrypter(base64_decode(substr($newKey, 7)), 'aes-256-cbc'))->decryptString($newCipher));

        DB::table('staff_accounts')->where('id', $staff->id)->update(['two_factor_secret' => 'garbage-not-encrypted']);
        $this->artisan('secrets:reencrypt')->assertFailed();
    }

    public function test_secret_plugin_settings_are_encrypted_at_rest_hidden_from_the_panel_and_readable_by_the_plugin(): void
    {
        DB::table('extensions')->insert([
            'id' => 'pay-plugin', 'name' => 'Pay', 'version' => '1.0.0', 'type' => 'plugin', 'status' => 'enabled',
            'manifest' => json_encode(['settings' => [['key' => 'api_key', 'type' => 'secret', 'label' => 'API key', 'default' => null], ['key' => 'mode', 'type' => 'string', 'label' => 'Mode', 'default' => 'live']]]),
            'installed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.extensions.settings.update', 'pay-plugin'), ['values' => ['api_key' => 'sk_live_topsecret', 'mode' => 'test']])->assertSessionHasNoErrors();

        $raw = (string) DB::table('extension_settings')->where('key', 'api_key')->value('value');
        $this->assertStringNotContainsString('sk_live_topsecret', $raw);
        $this->assertSame('sk_live_topsecret', app(ExtensionSettingsStore::class)->get('pay-plugin', 'api_key'));
        $this->assertSame('test', app(ExtensionSettingsStore::class)->all('pay-plugin')['mode']);

        $this->actingAs($staff)->get(route('panel.extensions.show', 'pay-plugin'))
            ->assertInertia(fn ($page) => $page
                ->where('settings_values', fn ($values) => ! isset($values['api_key']) && $values['mode'] === 'test')
                ->where('settings_schema.0.has_value', true));

        $this->actingAs($staff)->put(route('panel.extensions.settings.update', 'pay-plugin'), ['values' => ['api_key' => '', 'mode' => 'live']])->assertSessionHasNoErrors();
        $this->assertSame('sk_live_topsecret', app(ExtensionSettingsStore::class)->get('pay-plugin', 'api_key'));
    }
}
