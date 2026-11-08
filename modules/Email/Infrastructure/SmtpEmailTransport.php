<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransport;
use Litecommerce\Modules\Email\Contracts\EmailTransportException;
use Litecommerce\Modules\Email\Contracts\EmailTransportResult;
use Litecommerce\Modules\Email\Contracts\PreparedEmail;
use Litecommerce\Modules\Email\Contracts\TransportFailureKind;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class SmtpEmailTransport implements EmailTransport
{
    public function __construct(private readonly EmailSettings $settings) {}

    public function send(PreparedEmail $email): EmailTransportResult
    {
        $settings = $this->settings->current();
        $transport = new EsmtpTransport($settings->smtpHost, $settings->smtpPort, $settings->smtpEncryption === 'ssl');
        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout((float) config('email.smtp_timeout', 15));
        }
        if ($settings->smtpEncryption === 'tls') {
            $transport->setRequireTls(true);
        } elseif ($settings->smtpEncryption === null) {
            $transport->setAutoTls(false);
        }
        if ($settings->smtpUsername !== null) {
            $transport->setUsername($settings->smtpUsername);
        }
        $password = $this->settings->smtpPassword();
        if ($password !== null) {
            $transport->setPassword($password);
        }

        $message = (new Email)
            ->from(new Address($email->fromEmail, $email->fromName))
            ->to(new Address($email->toEmail, $email->toName ?? ''))
            ->subject($email->subject)
            ->html($email->html)
            ->text($email->text);
        if ($email->replyTo !== null) {
            $message->replyTo($email->replyTo);
        }

        $streams = [];
        try {
            foreach ($email->attachments as $attachment) {
                $stream = Storage::disk($attachment->disk)->readStream($attachment->path);
                if (! is_resource($stream)) {
                    throw new EmailTransportException(TransportFailureKind::Permanent, 'Stored email attachment is unavailable.', 'attachment_missing');
                }
                $streams[] = $stream;
                $message->addPart(new DataPart($stream, $attachment->filename, $attachment->mimeType));
            }

            $sent = $transport->send($message);

            return new EmailTransportResult($sent?->getMessageId());
        } catch (EmailTransportException $exception) {
            throw $exception;
        } catch (TransportExceptionInterface $exception) {
            throw $this->classify($exception);
        } finally {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    private function classify(TransportExceptionInterface $exception): EmailTransportException
    {
        $message = $exception->getMessage();
        $kind = match (true) {
            preg_match('/timed?\s*out|timeout|broken pipe|connection reset/i', $message) === 1 => TransportFailureKind::Unknown,
            preg_match('/\b5\d\d\b/', $message) === 1 => TransportFailureKind::Permanent,
            default => TransportFailureKind::Temporary,
        };

        return new EmailTransportException($kind, 'SMTP transport failed.', 'smtp_transport_error', $exception);
    }
}
