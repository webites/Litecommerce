<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure;

use App\Support\Secrets\ColumnReencryptor;
use App\Support\Secrets\SecretReencryptor;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;

final class ExtensionSecretReencryptor implements SecretReencryptor
{
    public function reencrypt(int $batchSize): array
    {
        $done = 0;
        $broken = 0;

        foreach (Extension::query()->get() as $extension) {
            foreach (EloquentExtensionSettingsStore::secretKeys($extension->manifest) as $key) {
                [$ok, $bad] = ColumnReencryptor::table(
                    'extension_settings', 'id', ['value'], $batchSize,
                    static fn ($query) => $query->where('extension_id', $extension->id)->where('key', $key),
                );
                $done += $ok;
                $broken += $bad;
            }
        }

        return [$done, $broken];
    }
}
