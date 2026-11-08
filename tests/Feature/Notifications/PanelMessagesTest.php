<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Notifications\Contracts\PanelInbox;
use Litecommerce\Modules\Notifications\Contracts\PanelMessage;
use Litecommerce\Modules\Notifications\Contracts\PanelMessageRegistry;
use Litecommerce\Modules\Notifications\Contracts\PanelMessageSeverity;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class PanelMessagesTest extends TestCase
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

    public function test_plugins_publish_messages_that_are_ordered_by_severity_and_limited_by_permission(): void
    {
        $this->register('acme', [
            new PanelMessage('acme.low', PanelMessageSeverity::Info, 'Info'),
            new PanelMessage('acme.admin_only', PanelMessageSeverity::Danger, 'Admin', permission: 'core.staff.manage'),
            new PanelMessage('acme.high', PanelMessageSeverity::Warning, 'Warn', 'Body', 'Fix it', '/panel/acme'),
        ]);
        $admin = StaffAccount::factory()->create();
        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();

        $this->assertSame(['acme.admin_only', 'acme.high', 'acme.low'], $this->keys(app(PanelInbox::class)->active($admin->getKey())));
        $this->assertSame(['acme.high', 'acme.low'], $this->keys(app(PanelInbox::class)->active($editor->getKey())));
    }

    public function test_a_broken_or_foreign_provider_cannot_break_the_panel_or_hijack_keys(): void
    {
        app(PanelMessageRegistry::class)->register('broken', static fn () => throw new \RuntimeException('plugin bug'));
        $this->register('acme', [
            new PanelMessage('acme.ok', PanelMessageSeverity::Info, 'Ok'),
            new PanelMessage('core.updates.available:9.9.9', PanelMessageSeverity::Danger, 'Spoofed core message'),
        ]);

        $this->assertSame(['acme.ok'], $this->keys(app(PanelInbox::class)->active(StaffAccount::factory()->create()->getKey())));
    }

    public function test_read_state_is_per_staff_member_and_a_new_key_is_unread_again(): void
    {
        $messages = [new PanelMessage('acme.alert:1', PanelMessageSeverity::Warning, 'First')];
        app(PanelMessageRegistry::class)->register('acme', static function () use (&$messages): array {
            return $messages;
        });
        $one = StaffAccount::factory()->create();
        $two = StaffAccount::factory()->create();
        $inbox = app(PanelInbox::class);

        $this->assertTrue($inbox->markRead($one->getKey(), 'acme.alert:1'));
        $this->assertSame([], $inbox->unread($one->getKey()));
        $this->assertSame(['acme.alert:1'], $this->keys($inbox->unread($two->getKey())));
        $this->assertSame(['acme.alert:1'], $this->keys($inbox->active($one->getKey())));

        $messages = [new PanelMessage('acme.alert:2', PanelMessageSeverity::Warning, 'Second')];
        $this->assertSame(['acme.alert:2'], $this->keys($inbox->unread($one->getKey())));
    }

    public function test_only_messages_shown_to_the_staff_member_can_be_marked_read(): void
    {
        $this->register('acme', [new PanelMessage('acme.admin_only', PanelMessageSeverity::Info, 'Admin', permission: 'core.staff.manage')]);
        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();

        $this->assertFalse(app(PanelInbox::class)->markRead($editor->getKey(), 'acme.admin_only'));
        $this->assertFalse(app(PanelInbox::class)->markRead($editor->getKey(), 'made.up'));
        $this->assertSame(0, DB::table('panel_message_reads')->count());
    }

    public function test_the_panel_shares_the_top_unread_message_as_a_banner_and_the_dashboard_lists_everything(): void
    {
        $this->register('acme', [
            new PanelMessage('acme.low', PanelMessageSeverity::Info, 'Low'),
            new PanelMessage('acme.high', PanelMessageSeverity::Danger, 'High'),
        ]);
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.cms.menus.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('panelBanner.message.key', 'acme.high')
            ->where('panelBanner.more', 1));

        $this->actingAs($staff)->post(route('panel.messages.read'), ['key' => 'acme.high'])->assertRedirect();

        $this->actingAs($staff)->get(route('panel.cms.menus.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('panelBanner.message.key', 'acme.low')
            ->where('panelBanner.more', 0));

        $this->actingAs($staff)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Panel/Dashboard')
            ->has('messages', 2)
            ->where('messages.0.key', 'acme.high')
            ->where('messages.0.read', true)
            ->where('messages.1.read', false));
    }

    public function test_marking_read_needs_a_signed_in_staff_member(): void
    {
        $this->post(route('panel.messages.read'), ['key' => 'acme.high'])->assertRedirect();
        $this->assertSame(0, DB::table('panel_message_reads')->count());
    }

    public function test_a_message_validates_its_key_and_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PanelMessage('x', PanelMessageSeverity::Info, 'Bad action', null, 'Go', 'javascript:alert(1)');
    }

    public function test_an_action_label_without_a_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PanelMessage('acme.x', PanelMessageSeverity::Info, 'Half action', null, 'Go');
    }

    /** @param list<PanelMessage> $messages */
    private function register(string $source, array $messages): void
    {
        app(PanelMessageRegistry::class)->register($source, static fn (): array => $messages);
    }

    /**
     * @param  list<PanelMessage>  $messages
     * @return list<string>
     */
    private function keys(array $messages): array
    {
        return array_map(static fn (PanelMessage $message): string => $message->key, $messages);
    }
}
