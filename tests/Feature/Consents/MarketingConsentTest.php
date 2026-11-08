<?php

declare(strict_types=1);

namespace Tests\Feature\Consents;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Litecommerce\Modules\Consents\Contracts\ConsentSource;
use Litecommerce\Modules\Consents\Contracts\Events\MarketingConsentGranted;
use Litecommerce\Modules\Consents\Contracts\Events\MarketingConsentWithdrawn;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class MarketingConsentTest extends TestCase
{
    use UsesIsolatedStorage;

    private MarketingConsentRegistry $consents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        foreach ([['pl', true], ['en', false]] as [$code, $default]) {
            DB::table('languages')->insert(['code' => $code, 'name' => $code, 'is_default' => $default, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->consents = app(MarketingConsentRegistry::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_granting_records_the_statement_shown_and_is_idempotent(): void
    {
        Event::fake([MarketingConsentGranted::class]);

        $this->assertTrue($this->consents->grant(' Jan@Example.com ', ConsentSource::Checkout, 'en'));
        $this->assertFalse($this->consents->grant('jan@example.com', ConsentSource::Account, 'pl'));

        $this->assertTrue($this->consents->isGranted('JAN@example.com'));
        $this->assertDatabaseCount('marketing_consents', 1);
        $this->assertDatabaseCount('marketing_consent_events', 1);
        $event = DB::table('marketing_consent_events')->first();
        $this->assertSame('granted', $event->action);
        $this->assertSame('checkout', $event->source);
        $this->assertSame('en', $event->locale);
        $this->assertSame(trans('consents::marketing.statement', [], 'en'), $event->content_text);
        $this->assertSame(substr(hash('sha256', $event->content_text), 0, 12), $event->content_version);
        Event::assertDispatchedTimes(MarketingConsentGranted::class, 1);
    }

    public function test_an_unknown_locale_falls_back_to_the_default_language(): void
    {
        $this->consents->grant('a@example.com', ConsentSource::Registration, 'xx');

        $this->assertSame('pl', DB::table('marketing_consents')->value('locale'));
    }

    public function test_withdrawing_keeps_the_proof_and_a_later_grant_adds_a_new_event(): void
    {
        Event::fake([MarketingConsentWithdrawn::class]);
        $this->consents->grant('a@example.com', ConsentSource::Checkout, 'pl');

        $this->assertTrue($this->consents->withdraw('a@example.com', ConsentSource::Account));
        $this->assertFalse($this->consents->withdraw('a@example.com', ConsentSource::Account));
        $this->assertFalse($this->consents->withdraw('nobody@example.com', ConsentSource::Account));
        $this->assertFalse($this->consents->isGranted('a@example.com'));
        $this->assertNotNull(DB::table('marketing_consents')->value('withdrawn_at'));

        $this->assertTrue($this->consents->grant('a@example.com', ConsentSource::Account, 'pl'));
        $this->assertNull(DB::table('marketing_consents')->value('withdrawn_at'));
        $this->assertSame(['granted', 'withdrawn', 'granted'], DB::table('marketing_consent_events')->orderBy('id')->pluck('action')->all());
        Event::assertDispatchedTimes(MarketingConsentWithdrawn::class, 1);
    }

    public function test_unsubscribe_link_withdraws_without_login_and_does_not_reveal_unknown_tokens(): void
    {
        $this->consents->grant('a@example.com', ConsentSource::Checkout, 'pl');
        $url = (string) $this->consents->unsubscribeUrl('a@example.com');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertStringStartsWith('http', $url);
        $this->assertSame(40, strlen($token));

        $this->postJson('/marketing/unsubscribe/'.str_repeat('z', 40))->assertOk();
        $this->assertTrue($this->consents->isGranted('a@example.com'));

        $this->postJson('/marketing/unsubscribe/'.$token)->assertOk();
        $this->postJson('/marketing/unsubscribe/'.$token)->assertOk();

        $this->assertFalse($this->consents->isGranted('a@example.com'));
        $this->assertNull($this->consents->unsubscribeUrl('a@example.com'));
        $this->assertSame('unsubscribe_link', DB::table('marketing_consent_events')->where('action', 'withdrawn')->value('source'));
    }

    public function test_senders_page_through_active_consents_with_a_cursor(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->consents->grant($name.'@example.com', ConsentSource::Checkout, 'pl');
        }
        $this->consents->withdraw('b@example.com', ConsentSource::Account);

        $first = $this->consents->grantedAfter(0, 2);
        $second = $this->consents->grantedAfter($first[1]->id, 2);

        $this->assertSame(['a@example.com', 'c@example.com'], array_map(static fn ($item) => $item->email, $first));
        $this->assertSame(['d@example.com'], array_map(static fn ($item) => $item->email, $second));
        $this->assertSame([], $this->consents->grantedAfter($second[0]->id, 2));
    }

    public function test_registration_records_the_consent_only_when_ticked(): void
    {
        $this->postJson(route('customer.register'), ['email' => 'plain@example.com', 'password' => 'Secret-Pass-123!', 'password_confirmation' => 'Secret-Pass-123!'])->assertCreated();
        $this->assertFalse($this->consents->isGranted('plain@example.com'));

        $this->postJson(route('customer.register'), ['email' => 'yes@example.com', 'password' => 'Secret-Pass-123!', 'password_confirmation' => 'Secret-Pass-123!', 'marketing_consent' => true, 'locale' => 'en'])->assertCreated();
        $this->assertTrue($this->consents->isGranted('yes@example.com'));
        $this->assertDatabaseHas('marketing_consent_events', ['source' => 'registration', 'locale' => 'en']);
    }

    public function test_the_account_preferences_endpoint_needs_a_login_and_toggles_the_consent_of_that_account(): void
    {
        $this->putJson(route('customer.account.marketing-consent.update'), ['granted' => true])->assertUnauthorized();
        $customer = CustomerAccount::query()->create(['email' => 'me@example.com', 'password' => 'Secret-Pass-123!']);

        $this->actingAs($customer, 'customer')->putJson(route('customer.account.marketing-consent.update'), ['granted' => true, 'locale' => 'pl'])->assertOk()->assertJsonPath('granted', true);
        $this->assertDatabaseHas('marketing_consent_events', ['source' => 'account', 'action' => 'granted']);

        $this->actingAs($customer, 'customer')->putJson(route('customer.account.marketing-consent.update'), ['granted' => false])->assertOk()->assertJsonPath('granted', false);
        $this->assertFalse($this->consents->isGranted('me@example.com'));
    }

    public function test_deleting_the_account_withdraws_the_consent_for_its_address(): void
    {
        $customer = CustomerAccount::query()->create(['email' => 'gone@example.com', 'password' => 'Secret-Pass-123!']);
        $this->consents->grant('gone@example.com', ConsentSource::Checkout, 'pl');
        $this->actingAs($customer, 'customer')->postJson(route('customer.account.deletion.request'), ['password' => 'Secret-Pass-123!'])->assertOk();
        $message = EmailMessage::query()->where('definition_key', 'core.customer_account_deletion_confirm')->firstOrFail();

        $this->postJson(route('customer.account.deletion.confirm'), ['token' => basename((string) parse_url((string) $message->variables['confirm_url'], PHP_URL_PATH))])->assertOk();

        $this->assertFalse($this->consents->isGranted('gone@example.com'));
        $this->assertSame('account_deletion', DB::table('marketing_consent_events')->where('action', 'withdrawn')->value('source'));
    }

    public function test_the_proof_of_a_withdrawn_consent_is_pruned_after_the_retention_period_and_active_ones_never(): void
    {
        $this->assertContains('core.consents.prune_withdrawn', array_map(static fn ($task): string => $task->key, app(ScheduledTaskRegistry::class)->all()));
        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->consents->grant('old@example.com', ConsentSource::Checkout, 'pl');
        $this->consents->grant('recent@example.com', ConsentSource::Checkout, 'pl');
        $this->consents->grant('active@example.com', ConsentSource::Checkout, 'pl');
        $this->consents->withdraw('old@example.com', ConsentSource::Account);
        Carbon::setTestNow('2026-06-02 12:00:00');
        $this->consents->withdraw('recent@example.com', ConsentSource::Account);

        Carbon::setTestNow('2032-06-01 12:30:00');
        $this->artisan('consents:prune-withdrawn')->assertSuccessful();
        $this->assertDatabaseMissing('marketing_consents', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('marketing_consents', ['email' => 'recent@example.com']);

        Carbon::setTestNow('2032-06-03 12:30:00');
        $this->artisan('consents:prune-withdrawn')->assertSuccessful();
        $this->assertDatabaseMissing('marketing_consents', ['email' => 'recent@example.com']);
        $this->assertDatabaseHas('marketing_consents', ['email' => 'active@example.com']);
        $this->assertSame(1, DB::table('marketing_consent_events')->count());
    }
}
