<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure;

use Illuminate\Support\Facades\Crypt;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailSettingsData;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

final class EloquentEmailSettings implements EmailSettings
{
    private const PasswordKey = 'email.smtp.password_encrypted';

    public function __construct(private readonly StoreSettingsStore $settings) {}

    public function current(): EmailSettingsData
    {
        return new EmailSettingsData(
            (string) $this->settings->get('email.from_name', config('mail.from.name', 'Litecommerce')),
            (string) $this->settings->get('email.from_address', config('mail.from.address', 'hello@example.com')),
            $this->nullable($this->settings->get('email.reply_to')),
            (string) $this->settings->get('email.transport', 'smtp'),
            (string) $this->settings->get('email.smtp.host', config('mail.mailers.smtp.host', '127.0.0.1')),
            (int) $this->settings->get('email.smtp.port', config('mail.mailers.smtp.port', 25)),
            $this->nullable($this->settings->get('email.smtp.encryption', config('mail.mailers.smtp.scheme'))),
            $this->nullable($this->settings->get('email.smtp.username', config('mail.mailers.smtp.username'))),
            $this->settings->has(self::PasswordKey) || filled(config('mail.mailers.smtp.password')),
        );
    }

    public function update(array $values): void
    {
        $stored = [
            'email.from_name' => trim((string) $values['from_name']),
            'email.from_address' => strtolower(trim((string) $values['from_email'])),
            'email.reply_to' => $this->nullable($values['reply_to'] ?? null),
            'email.transport' => (string) ($values['transport_key'] ?? 'smtp'),
            'email.smtp.host' => trim((string) $values['smtp_host']),
            'email.smtp.port' => (int) $values['smtp_port'],
            'email.smtp.encryption' => $this->nullable($values['smtp_encryption'] ?? null),
            'email.smtp.username' => $this->nullable($values['smtp_username'] ?? null),
        ];
        if (filled($values['smtp_password'] ?? null)) {
            $stored[self::PasswordKey] = Crypt::encryptString((string) $values['smtp_password']);
        }
        $this->settings->setMany($stored);
    }

    public function smtpPassword(): ?string
    {
        $encrypted = $this->settings->get(self::PasswordKey);
        if (is_string($encrypted) && $encrypted !== '') {
            return Crypt::decryptString($encrypted);
        }

        return $this->nullable(config('mail.mailers.smtp.password'));
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
