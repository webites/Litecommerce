<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Litecommerce\Modules\Cms\Infrastructure\InMemoryGlobalTagRegistry;
use Tests\TestCase;

/**
 * Cms\Infrastructure\InMemoryGlobalTagRegistry — {{tag}} values available on
 * EVERY page (archetype or plain Cms page), as opposed to ArchetypeRegistry's
 * per-archetype tags. Empty by default; this is the extension point a future
 * plugin/theme registers into (see docs/knowledge-base/09-presentation.md).
 */
final class GlobalTagRegistryTest extends TestCase
{
    public function test_all_lists_registered_tags_in_registration_order(): void
    {
        $registry = new InMemoryGlobalTagRegistry;
        $registry->register('store_name', 'Nazwa sklepu', fn () => 'Litecommerce');
        $registry->register('current_year', 'Bieżący rok', fn () => '2026');

        $this->assertSame(
            [
                ['name' => 'store_name', 'label' => 'Nazwa sklepu'],
                ['name' => 'current_year', 'label' => 'Bieżący rok'],
            ],
            $registry->all(),
        );
    }

    public function test_resolve_returns_every_tags_value_for_the_given_locale(): void
    {
        $registry = new InMemoryGlobalTagRegistry;
        $registry->register('store_name', 'Nazwa sklepu', fn (string $locale) => "Litecommerce ({$locale})");

        $this->assertSame(['store_name' => 'Litecommerce (pl)'], $registry->resolve('pl'));
    }

    public function test_a_failing_tag_resolver_is_skipped_and_others_still_resolve(): void
    {
        $registry = new InMemoryGlobalTagRegistry;
        $registry->register('broken', 'Uszkodzony', function (): never {
            throw new \RuntimeException('boom');
        });
        $registry->register('store_name', 'Nazwa sklepu', fn () => 'Litecommerce');

        $this->assertSame(['store_name' => 'Litecommerce'], $registry->resolve('pl'));
    }
}
