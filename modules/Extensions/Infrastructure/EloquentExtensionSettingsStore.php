<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Extensions\Contracts\ExtensionSettingsStore;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use Litecommerce\Modules\Extensions\Infrastructure\Models\ExtensionSetting;

final class EloquentExtensionSettingsStore implements ExtensionSettingsStore
{
    public function get(string $extensionId, string $key, ?string $default = null): ?string
    {
        $value = ExtensionSetting::query()->where('extension_id', $extensionId)->where('key', $key)->value('value');
        if ($value !== null && in_array($key, $this->secretKeysOf($extensionId), true)) {
            $value = $this->decrypt($extensionId, $key, (string) $value);
        }

        return $value ?? $default;
    }

    public function all(string $extensionId): array
    {
        $secretKeys = $this->secretKeysOf($extensionId);
        $values = ExtensionSetting::query()->where('extension_id', $extensionId)->pluck('value', 'key')->all();

        foreach ($values as $key => $value) {
            if (in_array($key, $secretKeys, true) && $value !== null) {
                $decrypted = $this->decrypt($extensionId, (string) $key, (string) $value);
                if ($decrypted === null) {
                    unset($values[$key]);
                } else {
                    $values[$key] = $decrypted;
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    public static function secretKeys(array $manifest): array
    {
        $keys = [];
        foreach ($manifest['settings'] ?? [] as $field) {
            if (($field['type'] ?? null) === 'secret') {
                $keys[] = (string) $field['key'];
            }
        }

        return $keys;
    }

    /** @return list<string> */
    private function secretKeysOf(string $extensionId): array
    {
        $manifest = Extension::query()->whereKey($extensionId)->value('manifest');

        return is_array($manifest) ? self::secretKeys($manifest) : [];
    }

    private function decrypt(string $extensionId, string $key, string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            Log::warning('A secret extension setting could not be decrypted.', ['extension_id' => $extensionId, 'setting' => $key]);

            return null;
        }
    }
}
