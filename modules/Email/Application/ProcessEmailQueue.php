<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransportException;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;
use Litecommerce\Modules\Email\Contracts\PreparedEmail;
use Litecommerce\Modules\Email\Contracts\PreparedEmailAttachment;
use Litecommerce\Modules\Email\Contracts\TransportFailureKind;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailSendAttempt;
use Throwable;

final class ProcessEmailQueue
{
    public function __construct(
        private readonly EmailTransportRegistry $transports,
        private readonly EmailSettings $settings,
        private readonly SafeTemplateRenderer $renderer,
    ) {}

    public function process(int $limit, int $timeLimitSeconds): int
    {
        $started = microtime(true);
        $processed = 0;

        while ($processed < max(1, $limit) && microtime(true) - $started < max(1, $timeLimitSeconds)) {
            $message = $this->claim();
            if ($message === null) {
                break;
            }
            $this->send($message);
            $processed++;
        }

        return $processed;
    }

    private function claim(): ?EmailMessage
    {
        return DB::transaction(function (): ?EmailMessage {
            EmailMessage::query()
                ->where('status', 'processing')
                ->where('leased_until', '<', now())
                ->update([
                    'status' => 'delivery_unknown',
                    'terminal_at' => now(),
                    'lease_token' => null,
                    'leased_until' => null,
                    'last_error_code' => 'lease_expired',
                    'last_error_message' => 'The worker stopped before recording the delivery result.',
                ]);

            $message = EmailMessage::query()
                ->where('status', 'queued')
                ->where('available_at', '<=', now())
                ->orderByDesc('priority')
                ->orderBy('available_at')
                ->lockForUpdate()
                ->first();
            if (! $message instanceof EmailMessage) {
                return null;
            }

            $message->update([
                'status' => 'processing',
                'lease_token' => (string) Str::uuid(),
                'leased_until' => now()->addSeconds((int) config('email.lease_seconds', 120)),
            ]);

            return $message->fresh(['attachments']);
        });
    }

    private function send(EmailMessage $message): void
    {
        $attemptNumber = (int) $message->attempts + 1;
        $attempt = EmailSendAttempt::query()->create([
            'email_message_id' => $message->id,
            'attempt_number' => $attemptNumber,
            'transport_key' => $message->transport_key,
            'outcome' => 'started',
            'started_at' => now(),
        ]);
        $started = microtime(true);

        try {
            $transport = $this->transports->find($message->transport_key);
            if ($transport === null) {
                throw new EmailTransportException(TransportFailureKind::Permanent, 'Configured email transport is unavailable.', 'transport_unavailable');
            }
            if ($message->rendered_html === null || $message->rendered_subject === null || $message->rendered_text === null) {
                $subject = $this->renderer->subject((string) $message->template_subject, (array) $message->variables);
                $html = $this->renderer->html((string) $message->template_html, (string) $message->layout_html, (array) $message->variables);
                if ($message->mode === 'test') {
                    $subject = '[TEST] '.$subject;
                    $html = '<p><strong>TEST</strong></p>'.$html;
                }
                $message->update([
                    'rendered_subject' => $subject,
                    'rendered_html' => $html,
                    'rendered_text' => trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                ]);
                $message->refresh();
            }

            $settings = $this->settings->current();
            $result = $transport->send(new PreparedEmail(
                $message->id,
                (string) $message->to_email,
                $message->to_name,
                $settings->fromEmail,
                $settings->fromName,
                $settings->replyTo,
                (string) $message->rendered_subject,
                (string) $message->rendered_html,
                (string) $message->rendered_text,
                $message->attachments->map(static fn ($attachment): PreparedEmailAttachment => new PreparedEmailAttachment(
                    $attachment->filename, $attachment->mime_type, $attachment->disk, $attachment->path,
                    (int) $attachment->size_bytes, $attachment->checksum_sha256,
                ))->all(),
            ));

            $attempt->update([
                'outcome' => 'accepted',
                'provider_message_id' => $result->providerMessageId,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'finished_at' => now(),
            ]);
            $message->update([
                'status' => 'sent',
                'attempts' => $attemptNumber,
                'accepted_at' => now(),
                'terminal_at' => now(),
                'lease_token' => null,
                'leased_until' => null,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);
            $this->scrubSecurityMessage($message);
        } catch (EmailTransportException $exception) {
            $this->failed($message, $attempt, $attemptNumber, $exception, $started);
        } catch (Throwable $exception) {
            $this->failed($message, $attempt, $attemptNumber, new EmailTransportException(
                TransportFailureKind::Permanent,
                'Email rendering or delivery failed.',
                'email_processing_error',
                $exception,
            ), $started);
        }
    }

    private function failed(EmailMessage $message, EmailSendAttempt $attempt, int $attemptNumber, EmailTransportException $exception, float $started): void
    {
        $maxAttempts = (int) config('email.max_attempts', 6);
        $retry = $exception->kind === TransportFailureKind::Temporary && $attemptNumber < $maxAttempts;
        $status = $exception->kind === TransportFailureKind::Unknown ? 'delivery_unknown' : ($retry ? 'queued' : 'failed');
        $safeMessage = mb_substr($exception->getMessage(), 0, 1000);

        $attempt->update([
            'outcome' => $exception->kind->value,
            'error_code' => $exception->safeCode,
            'error_message' => $safeMessage,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'finished_at' => now(),
        ]);

        $backoff = (array) config('email.backoff_seconds', [60, 300, 1800]);
        $delay = (int) ($backoff[min($attemptNumber - 1, count($backoff) - 1)] ?? 60);
        $message->update([
            'status' => $status,
            'attempts' => $attemptNumber,
            'available_at' => $retry ? now()->addSeconds($delay) : $message->available_at,
            'terminal_at' => $retry ? null : now(),
            'lease_token' => null,
            'leased_until' => null,
            'last_error_code' => $exception->safeCode,
            'last_error_message' => $safeMessage,
        ]);
    }

    private function scrubSecurityMessage(EmailMessage $message): void
    {
        if (! in_array($message->definition_key, ['core.staff_password_reset', 'core.customer_password_reset'], true)) {
            return;
        }
        $message->update([
            'variables' => [],
            'rendered_subject' => null,
            'rendered_html' => null,
            'rendered_text' => null,
        ]);
    }
}
