<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure;

use App\Support\Secrets\ColumnReencryptor;
use App\Support\Secrets\SecretReencryptor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

final class EmailSecretReencryptor implements SecretReencryptor
{
    private const PasswordKey = 'email.smtp.password_encrypted';

    public function __construct(private readonly StoreSettingsStore $settings) {}

    public function reencrypt(int $batchSize): array
    {
        [$done, $broken] = ColumnReencryptor::table('email_messages', 'id', [
            'to_email', 'to_name', 'variables', 'template_subject', 'template_html', 'layout_html',
            'rendered_subject', 'rendered_html', 'rendered_text',
        ], $batchSize);

        $stored = $this->settings->get(self::PasswordKey);
        if (is_string($stored) && $stored !== '') {
            try {
                $this->settings->set(self::PasswordKey, Crypt::encryptString(Crypt::decryptString($stored)));
                $done++;
            } catch (DecryptException) {
                $broken++;
            }
        }

        return [$done, $broken];
    }
}
