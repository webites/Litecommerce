<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use Illuminate\Support\Str;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransportException;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;
use Litecommerce\Modules\Email\Contracts\PreparedEmail;
use Litecommerce\Modules\Email\Contracts\TestEmailSender;
use Litecommerce\Modules\Email\Contracts\TransportFailureKind;

final readonly class SendTestEmail implements TestEmailSender
{
    public function __construct(private EmailSettings $settings, private EmailTransportRegistry $transports) {}

    public function send(string $toEmail, string $locale): void
    {
        $settings = $this->settings->current();
        $transport = $this->transports->find($settings->transportKey);
        if ($transport === null) {
            throw new EmailTransportException(TransportFailureKind::Permanent, 'Email transport is not available.', 'transport_missing');
        }

        $subject = (string) trans('email::test.subject', ['store' => $settings->fromName], $locale);
        $text = (string) trans('email::test.body', ['store' => $settings->fromName], $locale);

        $transport->send(new PreparedEmail(
            messageId: (string) Str::uuid(),
            toEmail: $toEmail,
            toName: null,
            fromEmail: $settings->fromEmail,
            fromName: $settings->fromName,
            replyTo: $settings->replyTo,
            subject: $subject,
            html: '<p>'.e($text).'</p>',
            text: $text,
            attachments: [],
        ));
    }
}
