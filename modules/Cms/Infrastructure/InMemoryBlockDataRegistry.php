<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Cms\Contracts\BlockDataRegistry;
use Throwable;

final class InMemoryBlockDataRegistry implements BlockDataRegistry
{
    /** @var array<string, \Closure(array<string, mixed>, string, array<string, mixed>): array<string, mixed>> */
    private array $resolvers = [];

    public function register(string $type, \Closure $resolver): void
    {
        $this->resolvers[$type] = $resolver;
    }

    public function resolve(string $type, array $props, string $locale, array $context): array
    {
        $resolver = $this->resolvers[$type] ?? null;

        if ($resolver === null) {
            return [];
        }

        try {
            return $resolver($props, $locale, $context);
        } catch (Throwable $exception) {
            Log::warning("Cms block data resolver [{$type}] failed.", ['exception' => $exception]);

            return [];
        }
    }
}
