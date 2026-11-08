<?php

declare(strict_types=1);

namespace App\Support\Secrets;

interface SecretReencryptor
{
    /** Re-encrypts stored secrets with the current APP_KEY and returns [reencrypted, undecryptable]. */
    public function reencrypt(int $batchSize): array;
}
