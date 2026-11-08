<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Throwable;

/** Bound as a singleton, repopulated every request as each module's/plugin's boot() registers its tags — same lifetime convention as InMemoryArchetypeRegistry. */
final class InMemoryGlobalTagRegistry implements GlobalTagRegistry
{
    /** @var list<array{name: string, label: string, resolve: \Closure(string): string}> */
    private array $tags = [];

    public function register(string $name, string $label, \Closure $resolve): void
    {
        $this->tags[] = ['name' => $name, 'label' => $label, 'resolve' => $resolve];
    }

    public function all(): array
    {
        return array_map(
            static fn (array $tag): array => ['name' => $tag['name'], 'label' => $tag['label']],
            $this->tags,
        );
    }

    public function resolve(string $locale): array
    {
        $resolved = [];

        foreach ($this->tags as $tag) {
            try {
                $resolved[$tag['name']] = (string) ($tag['resolve'])($locale);
            } catch (Throwable $exception) {
                Log::warning("Global tag [{$tag['name']}] failed to resolve.", ['exception' => $exception]);
            }
        }

        return $resolved;
    }
}
