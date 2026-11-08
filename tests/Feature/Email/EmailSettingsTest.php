<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class EmailSettingsTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_authorized_staff_can_save_encrypted_smtp_settings_and_safe_template(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->put(route('panel.email.update'), [
            'from_name' => 'Sklep', 'from_email' => 'store@example.com', 'reply_to' => 'reply@example.com',
            'transport_key' => 'smtp', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587,
            'smtp_encryption' => 'tls', 'smtp_username' => 'mailer', 'smtp_password' => 'smtp-secret',
            'status_notifications' => ['processing'],
        ])->assertSessionHasNoErrors();

        $rawPassword = (string) DB::table('store_settings')->where('key', 'email.smtp.password_encrypted')->value('value');
        $this->assertStringNotContainsString('smtp-secret', $rawPassword);
        $this->assertSame('smtp-secret', app(EmailSettings::class)->smtpPassword());

        $this->actingAs($staff)->put(route('panel.email.templates.update', ['definition' => 'core.order_placed', 'locale' => 'pl']), [
            'subject' => 'Zamówienie {{order_id}}',
            'html' => '<p onclick="alert(1)">{{order_id}}</p><a href="https://example.com">link</a>',
            'enabled' => true,
            'layout_id' => null,
        ])->assertSessionHasNoErrors();
        $html = (string) DB::table('email_templates')->where('definition_key', 'core.order_placed')->value('html');
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('https://example.com', $html);
        $this->assertStringContainsString('{{order_id}}', $html);
    }

    public function test_staff_without_email_permission_cannot_read_or_write_email_settings(): void
    {
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->getJson(route('panel.settings.tabs.show', 'email'))->assertNotFound();
        $this->actingAs($staff)->put(route('panel.email.update'), [])->assertForbidden();
    }
}
