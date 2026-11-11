<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Email\Application\ProcessEmailQueue;
use Litecommerce\Modules\Email\Contracts\EmailAttachmentSource;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransport;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;
use Litecommerce\Modules\Email\Contracts\EmailTransportResult;
use Litecommerce\Modules\Email\Contracts\PreparedEmail;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class EmailQueueTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        app(SetDefaultLanguage::class)->handle('en', 'English');
    }

    public function test_message_is_encrypted_at_rest_and_idempotent(): void
    {
        $envelope = $this->envelope('same-operation');
        $queue = app(TransactionalEmailQueue::class);

        $first = $queue->enqueue($envelope);
        $second = $queue->enqueue($envelope);

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('email_messages', 1);
        $raw = DB::table('email_messages')->first();
        $this->assertStringNotContainsString('buyer@example.com', (string) $raw->to_email);
        $this->assertStringNotContainsString('ORDER-SECRET', (string) $raw->variables);
        $message = EmailMessage::query()->firstOrFail();
        $this->assertSame('buyer@example.com', $message->to_email);
        $this->assertStringContainsString('Powered by <strong>Litecommerce</strong>', $message->layout_html);
    }

    public function test_processor_renders_and_sends_snapshot_once(): void
    {
        $transport = new class implements EmailTransport
        {
            /** @var list<PreparedEmail> */
            public array $sent = [];

            public function send(PreparedEmail $email): EmailTransportResult
            {
                $this->sent[] = $email;

                return new EmailTransportResult('provider-1');
            }
        };
        app(EmailTransportRegistry::class)->register('fake', $transport);
        app(EmailSettings::class)->update([
            'from_name' => 'Store', 'from_email' => 'store@example.com', 'reply_to' => null,
            'transport_key' => 'fake', 'smtp_host' => 'localhost', 'smtp_port' => 25,
            'smtp_encryption' => null, 'smtp_username' => null,
        ]);
        $id = app(TransactionalEmailQueue::class)->enqueue($this->envelope('send-once'));

        $this->assertSame(1, app(ProcessEmailQueue::class)->process(10, 5));
        $this->assertCount(1, $transport->sent);
        $this->assertStringContainsString('ORDER-SECRET', $transport->sent[0]->subject);
        $this->assertStringContainsString('View order', $transport->sent[0]->html);
        $this->assertDatabaseHas('email_messages', ['id' => $id, 'status' => 'sent', 'attempts' => 1]);
        $this->assertDatabaseHas('email_send_attempts', ['email_message_id' => $id, 'outcome' => 'accepted', 'provider_message_id' => 'provider-1']);
        $this->assertSame(0, app(ProcessEmailQueue::class)->process(10, 5));
    }

    public function test_expired_lease_is_marked_unknown_instead_of_blindly_resent(): void
    {
        $id = app(TransactionalEmailQueue::class)->enqueue($this->envelope('expired-worker'));
        EmailMessage::query()->whereKey($id)->update([
            'status' => 'processing',
            'leased_until' => now()->subMinute(),
        ]);

        $this->assertSame(0, app(ProcessEmailQueue::class)->process(10, 5));
        $this->assertDatabaseHas('email_messages', [
            'id' => $id,
            'status' => 'delivery_unknown',
            'last_error_code' => 'lease_expired',
        ]);
        $this->assertDatabaseCount('email_send_attempts', 0);
    }

    public function test_attachment_is_streamed_to_owned_private_copy_with_checksum(): void
    {
        $bytes = 'private-invoice-content';
        $source = new class($bytes) implements EmailAttachmentSource
        {
            public function __construct(private readonly string $bytes) {}

            public function filename(): string
            {
                return 'invoice.pdf';
            }

            public function mimeType(): string
            {
                return 'application/pdf';
            }

            public function sizeBytes(): int
            {
                return strlen($this->bytes);
            }

            public function openStream()
            {
                $stream = fopen('php://temp', 'w+b');
                fwrite($stream, $this->bytes);
                rewind($stream);

                return $stream;
            }
        };
        $base = $this->envelope('with-attachment');
        $message = new EmailEnvelope(
            $base->definitionKey, $base->toEmail, $base->toName, $base->locale, $base->variables,
            $base->idempotencyKey, attachments: [$source],
        );
        $id = app(TransactionalEmailQueue::class)->enqueue($message);

        $attachment = DB::table('email_message_attachments')->where('email_message_id', $id)->first();
        $this->assertNotNull($attachment);
        $this->assertSame(hash('sha256', $bytes), $attachment->checksum_sha256);
        $this->assertSame($bytes, file_get_contents(storage_path('app/private/'.$attachment->path)));
    }

    private function envelope(string $idempotencyKey): EmailEnvelope
    {
        return new EmailEnvelope(
            'core.order_placed',
            'buyer@example.com',
            'Buyer',
            'en',
            [
                'store_name' => 'Store', 'order_id' => 'ORDER-SECRET', 'order_number' => 'ORDER-SECRET', 'total' => '10.00', 'currency' => 'PLN',
                'order_url' => 'https://example.com/en/orders/token', 'lines' => [['name' => 'Product', 'quantity' => '1']],
            ],
            $idempotencyKey,
        );
    }
}
