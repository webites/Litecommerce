<?php

declare(strict_types=1);

namespace App\Support\Secrets;

use Illuminate\Console\Command;

final class ReencryptSecretsCommand extends Command
{
    protected $signature = 'secrets:reencrypt {--batch=500 : Rows per database chunk}';

    protected $description = 'Re-encrypt stored secrets with the current APP_KEY after a key rotation (old key goes to APP_PREVIOUS_KEYS)';

    public function handle(): int
    {
        $done = 0;
        $broken = 0;

        foreach (app()->tagged(SecretReencryptor::class) as $reencryptor) {
            [$ok, $bad] = $reencryptor->reencrypt(max(1, (int) $this->option('batch')));
            $done += $ok;
            $broken += $bad;
        }

        $this->info("Re-encrypted values: {$done}.");
        if ($broken > 0) {
            $this->error("Values that no configured key can decrypt: {$broken}. Keep the old key in APP_PREVIOUS_KEYS and investigate.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
