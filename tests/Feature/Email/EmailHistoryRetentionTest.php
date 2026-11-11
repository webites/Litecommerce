<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Email\Contracts\EmailAttachmentSource;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class EmailHistoryRetentionTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        app(SetDefaultLanguage::class)->handle('en', 'English');
        Carbon::setTestNow('2026-09-27 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_removes_only_terminal_email_history_older_than_one_year(): void
    {
        $expiredId = $this->enqueue('expired', true);
        $recentId = $this->enqueue('recent');
        $boundaryId = $this->enqueue('boundary');
        $activeId = $this->enqueue('active');

        EmailMessage::query()->whereKey($expiredId)->update([
            'status' => 'sent',
            'terminal_at' => now()->subYear()->subSecond(),
        ]);
        EmailMessage::query()->whereKey($recentId)->update([
            'status' => 'failed',
            'terminal_at' => now()->subDays(364),
        ]);
        EmailMessage::query()->whereKey($boundaryId)->update([
            'status' => 'cancelled',
            'terminal_at' => now()->subYear(),
        ]);
        EmailMessage::query()->whereKey($activeId)->update([
            'status' => 'queued',
            'terminal_at' => now()->subYears(2),
        ]);
        DB::table('email_send_attempts')->insert([
            'email_message_id' => $expiredId,
            'attempt_number' => 1,
            'transport_key' => 'smtp',
            'outcome' => 'accepted',
            'started_at' => now()->subYears(2),
            'finished_at' => now()->subYears(2),
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subYears(2),
        ]);

        $attachment = DB::table('email_message_attachments')->where('email_message_id', $expiredId)->firstOrFail();
        Storage::disk((string) $attachment->disk)->assertExists((string) $attachment->path);

        $this->artisan('email:prune-history')->assertSuccessful();

        $this->assertDatabaseMissing('email_messages', ['id' => $expiredId]);
        $this->assertDatabaseMissing('email_send_attempts', ['email_message_id' => $expiredId]);
        $this->assertDatabaseMissing('email_message_attachments', ['email_message_id' => $expiredId]);
        Storage::disk((string) $attachment->disk)->assertMissing((string) $attachment->path);
        $this->assertDatabaseHas('email_messages', ['id' => $recentId, 'status' => 'failed']);
        $this->assertDatabaseHas('email_messages', ['id' => $boundaryId, 'status' => 'cancelled']);
        $this->assertDatabaseHas('email_messages', ['id' => $activeId, 'status' => 'queued']);
    }

    public function test_each_run_is_bounded_by_batch_configuration(): void
    {
        foreach (['first', 'second', 'third'] as $key) {
            $id = $this->enqueue($key);
            EmailMessage::query()->whereKey($id)->update([
                'status' => 'delivery_unknown',
                'terminal_at' => now()->subYears(2),
            ]);
        }

        $this->artisan('email:prune-history', ['--batch' => 1, '--max-batches' => 2])->assertSuccessful();

        $this->assertDatabaseCount('email_messages', 1);
    }

    private function enqueue(string $key, bool $withAttachment = false): string
    {
        $attachments = $withAttachment ? [new class implements EmailAttachmentSource
        {
            public function filename(): string
            {
                return 'history.txt';
            }

            public function mimeType(): string
            {
                return 'text/plain';
            }

            public function sizeBytes(): int
            {
                return 15;
            }

            public function openStream()
            {
                $stream = fopen('php://temp', 'w+b');
                fwrite($stream, 'history payload');
                rewind($stream);

                return $stream;
            }
        }] : [];

        $id = app(TransactionalEmailQueue::class)->enqueue(new EmailEnvelope(
            'core.order_placed',
            'buyer@example.com',
            'Buyer',
            'en',
            [
                'store_name' => 'Store',
                'order_id' => 'ORDER-1', 'order_number' => '1001',
                'total' => '10.00',
                'currency' => 'PLN',
                'order_url' => 'https://example.com/en/orders/token',
                'lines' => [['name' => 'Product', 'quantity' => '1']],
            ],
            'history-'.$key,
            attachments: $attachments,
        ));

        $this->assertIsString($id);

        return $id;
    }
}
