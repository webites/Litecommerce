<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;
use Litecommerce\Modules\Cms\Infrastructure\InMemoryArchetypeRegistry;
use Tests\TestCase;

/**
 * Cms\Infrastructure\InMemoryArchetypeRegistry — the generic "custom post
 * type" analogue any module (or, later, a plugin) registers a whole class
 * of aggregating pages against, mirroring Storefront\Infrastructure\
 * InMemoryProductTabRegistry's contract (see docs/knowledge-base/09-presentation.md).
 */
final class ArchetypeRegistryTest extends TestCase
{
    public function test_all_lists_registered_archetypes_in_registration_order(): void
    {
        $registry = new InMemoryArchetypeRegistry;
        $registry->register('category', 'Kategoria', fn () => null);
        $registry->register('brand', 'Marka', fn () => null);

        $this->assertSame(
            [
                ['key' => 'category', 'label' => 'Kategoria', 'availableTags' => []],
                ['key' => 'brand', 'label' => 'Marka', 'availableTags' => []],
            ],
            $registry->all(),
        );
    }

    public function test_match_tries_resolvers_in_order_and_returns_the_first_hit(): void
    {
        $registry = new InMemoryArchetypeRegistry;
        $registry->register('category', 'Kategoria', fn (string $slug) => $slug === 'buty' ? new ArchetypeMatch('Buty', null, ['category_id' => 1]) : null);
        $registry->register('brand', 'Marka', fn (string $slug) => $slug === 'buty' ? new ArchetypeMatch('Buty (marka)', null, ['brand_id' => 2]) : null);

        $resolved = $registry->match('buty', 'pl');

        $this->assertNotNull($resolved);
        $this->assertSame('category', $resolved->key);
        $this->assertSame('Buty', $resolved->match->title);
    }

    public function test_match_returns_null_when_nothing_matches(): void
    {
        $registry = new InMemoryArchetypeRegistry;
        $registry->register('category', 'Kategoria', fn () => null);

        $this->assertNull($registry->match('nie-istnieje', 'pl'));
    }

    public function test_match_key_ignores_an_earlier_archetype_with_the_same_slug(): void
    {
        $registry = new InMemoryArchetypeRegistry;
        $registry->register('category', 'Kategoria produktu', fn () => new ArchetypeMatch('Odzież', null, ['category_id' => 1]));
        $registry->register('blog_category', 'Kategoria bloga', fn () => new ArchetypeMatch('Odzież na blogu', null, ['blog_category_id' => 2]));

        $resolved = $registry->matchKey('blog_category', 'odziez', 'pl');

        $this->assertNotNull($resolved);
        $this->assertSame('blog_category', $resolved->key);
        $this->assertSame('Odzież na blogu', $resolved->match->title);
    }

    public function test_a_resolver_exception_is_skipped_and_later_resolvers_still_run(): void
    {
        $registry = new InMemoryArchetypeRegistry;
        $registry->register('broken', 'Uszkodzony', function (): never {
            throw new \RuntimeException('boom');
        });
        $registry->register('category', 'Kategoria', fn (string $slug) => new ArchetypeMatch('Buty', null, ['category_id' => 1]));

        $resolved = $registry->match('buty', 'pl');

        $this->assertNotNull($resolved);
        $this->assertSame('category', $resolved->key);
    }
}
