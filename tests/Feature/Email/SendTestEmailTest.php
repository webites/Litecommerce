<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransport;
use Litecommerce\Modules\Email\Contracts\EmailTransportException;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;
use Litecommerce\Modules\Email\Contracts\EmailTransportResult;
use Litecommerce\Modules\Email\Contracts\PreparedEmail;
use Litecommerce\Modules\Email\Contracts\TestEmailSender;
use Litecommerce\Modules\Email\Infrastructure\InMemoryEmailTransportRegistry;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class SendTestEmailTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        app(EmailSettings::class)->update(['from_name' => 'Acme Store', 'from_email' => 'shop@example.com', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'transport_key' => 'smtp']);
    }

    public function test_the_message_is_sent_straight_through_the_transport_in_the_requested_language(): void
    {
        $sent = [];
        $registry = new InMemoryEmailTransportRegistry;
        app()->instance(EmailTransportRegistry::class, $registry);
        $registry->register('smtp', new class($sent) implements EmailTransport
        {
            /** @param list<PreparedEmail> $sent */
            public function __construct(public array &$sent) {}

            public function send(PreparedEmail $email): EmailTransportResult
            {
                $this->sent[] = $email;

                return new EmailTransportResult;
            }
        });

        app(TestEmailSender::class)->send('owner@example.com', 'pl');

        $this->assertCount(1, $sent);
        $this->assertSame('owner@example.com', $sent[0]->toEmail);
        $this->assertSame('shop@example.com', $sent[0]->fromEmail);
        $this->assertSame('Wiadomość testowa ze sklepu Acme Store', $sent[0]->subject);
        $this->assertStringContainsString('Acme Store', $sent[0]->text);
        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_a_missing_transport_is_reported_as_a_transport_exception(): void
    {
        app()->instance(EmailTransportRegistry::class, new class implements EmailTransportRegistry
        {
            public function register(string $key, EmailTransport $transport, ?string $owner = null): void {}

            public function find(string $key): ?EmailTransport
            {
                return null;
            }

            public function owner(string $key): ?string
            {
                return null;
            }

            public function keys(): array
            {
                return [];
            }
        });

        $this->expectException(EmailTransportException::class);

        app(TestEmailSender::class)->send('owner@example.com', 'en');
    }
}
