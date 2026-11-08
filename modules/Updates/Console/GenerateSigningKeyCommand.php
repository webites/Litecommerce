<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Console;

use Illuminate\Console\Command;

final class GenerateSigningKeyCommand extends Command
{
    protected $signature = 'updates:generate-signing-key';

    protected $description = 'Generate an Ed25519 key pair for signing releases (keep the secret key out of the repository)';

    public function handle(): int
    {
        $pair = sodium_crypto_sign_keypair();

        $this->line('Public key (add to UPDATES_TRUSTED_PUBLIC_KEYS): '.base64_encode(sodium_crypto_sign_publickey($pair)));
        $this->line('Secret key (store as the RELEASE_SIGNING_KEY secret, never commit it): '.base64_encode(sodium_crypto_sign_secretkey($pair)));

        return self::SUCCESS;
    }
}
