<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\ResolvedArchetype;
use Throwable;

/** Bound as a singleton, repopulated every request as each module's boot() registers its archetypes — same lifetime convention as InMemoryProductTabRegistry. */
final class InMemoryArchetypeRegistry implements ArchetypeRegistry
{
    /** @var list<array{key: string, label: string, resolve: \Closure(string, string): ?ArchetypeMatch, availableTags: array<string, string>, findSlugInLocale: ?\Closure(array<string, mixed>, string): ?string, listInstances: ?\Closure(string): array, resolveInstance: ?\Closure(string, string): ?array}> */
    private array $archetypes = [];

    public function register(string $key, string $label, \Closure $resolve, array $availableTags = [], ?\Closure $findSlugInLocale = null, ?\Closure $listInstances = null, ?\Closure $resolveInstance = null): void
    {
        $this->archetypes[] = [
            'key' => $key,
            'label' => $label,
            'resolve' => $resolve,
            'availableTags' => $availableTags,
            'findSlugInLocale' => $findSlugInLocale,
            'listInstances' => $listInstances,
            'resolveInstance' => $resolveInstance,
        ];
    }

    public function all(): array
    {
        return array_map(
            static fn (array $archetype): array => [
                'key' => $archetype['key'],
                'label' => $archetype['label'],
                'availableTags' => $archetype['availableTags'],
            ],
            $this->archetypes,
        );
    }

    public function match(string $slug, string $locale): ?ResolvedArchetype
    {
        foreach ($this->archetypes as $archetype) {
            $resolved = $this->resolveEntry($archetype, $slug, $locale);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    public function matchKey(string $key, string $slug, string $locale): ?ResolvedArchetype
    {
        foreach ($this->archetypes as $archetype) {
            if ($archetype['key'] === $key) {
                return $this->resolveEntry($archetype, $slug, $locale);
            }
        }

        return null;
    }

    public function findSlugInLocale(string $key, array $blockContext, string $locale): ?string
    {
        foreach ($this->archetypes as $archetype) {
            if ($archetype['key'] !== $key) {
                continue;
            }

            if ($archetype['findSlugInLocale'] === null) {
                return null;
            }

            try {
                return ($archetype['findSlugInLocale'])($blockContext, $locale);
            } catch (Throwable $exception) {
                Log::warning("Archetype [{$key}]'s findSlugInLocale failed.", ['exception' => $exception]);

                return null;
            }
        }

        return null;
    }

    public function listInstances(string $key, string $locale): array
    {
        foreach ($this->archetypes as $archetype) {
            if ($archetype['key'] !== $key) {
                continue;
            }

            if ($archetype['listInstances'] === null) {
                return [];
            }

            try {
                return ($archetype['listInstances'])($locale);
            } catch (Throwable $exception) {
                Log::warning("Archetype [{$key}]'s listInstances failed.", ['exception' => $exception]);

                return [];
            }
        }

        return [];
    }

    public function resolveInstance(string $key, string $id, string $locale): ?array
    {
        foreach ($this->archetypes as $archetype) {
            if ($archetype['key'] !== $key) {
                continue;
            }

            if ($archetype['resolveInstance'] === null) {
                return null;
            }

            try {
                return ($archetype['resolveInstance'])($id, $locale);
            } catch (Throwable $exception) {
                Log::warning("Archetype [{$key}]'s resolveInstance failed.", ['exception' => $exception]);

                return null;
            }
        }

        return null;
    }

    /** @param array{key: string, label: string, resolve: \Closure(string, string): ?ArchetypeMatch, availableTags: array<string, string>, findSlugInLocale: ?\Closure(array<string, mixed>, string): ?string, listInstances: ?\Closure(string): array, resolveInstance: ?\Closure(string, string): ?array} $archetype */
    private function resolveEntry(array $archetype, string $slug, string $locale): ?ResolvedArchetype
    {
        try {
            $match = ($archetype['resolve'])($slug, $locale);
        } catch (Throwable $exception) {
            Log::warning("Archetype resolver [{$archetype['key']}] failed while matching slug [{$slug}].", ['exception' => $exception]);

            return null;
        }

        return $match === null ? null : new ResolvedArchetype($archetype['key'], $match);
    }
}
