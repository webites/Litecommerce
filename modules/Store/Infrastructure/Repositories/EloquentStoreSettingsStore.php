<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure\Repositories;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use Litecommerce\Modules\Store\Contracts\Events\StoreSettingsChanged;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Litecommerce\Modules\Store\Infrastructure\Models\StoreSettings;

final class EloquentStoreSettingsStore implements StoreSettingsStore
{
    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKey($key);
        $encoded = StoreSettings::query()->whereKey($key)->value('value');

        return is_string($encoded) ? $this->decode($encoded) : $default;
    }

    public function has(string $key): bool
    {
        $this->assertKey($key);

        return StoreSettings::query()->whereKey($key)->exists();
    }

    public function all(?string $prefix = null): array
    {
        $query = StoreSettings::query()->orderBy('key');
        if ($prefix !== null) {
            $this->assertKey($prefix);
            $query->where('key', 'like', $prefix.'%');
        }

        return $query->pluck('value', 'key')
            ->map(fn (string $value): mixed => $this->decode($value))
            ->all();
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    public function setMany(array $values): void
    {
        if ($values === []) {
            return;
        }

        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $this->assertKey($key);
                StoreSettings::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $this->encode($value)],
                );
            }

            DB::afterCommit(static fn () => CommittedEvent::dispatch(new StoreSettingsChanged));
        });
    }

    public function remove(string $key): bool
    {
        $this->assertKey($key);
        $removed = false;

        DB::transaction(function () use ($key, &$removed): void {
            $removed = StoreSettings::query()->whereKey($key)->delete() > 0;
            if ($removed) {
                DB::afterCommit(static fn () => CommittedEvent::dispatch(new StoreSettingsChanged));
            }
        });

        return $removed;
    }

    private function assertKey(string $key): void
    {
        if (strlen($key) > 191 || preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid store setting key [{$key}].");
        }
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Store setting value must be JSON-serializable.', previous: $exception);
        }
    }

    private function decode(string $value): mixed
    {
        try {
            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Stored setting contains invalid JSON.', previous: $exception);
        }
    }
}
