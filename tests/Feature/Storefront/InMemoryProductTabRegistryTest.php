<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Litecommerce\Modules\Catalog\Contracts\ProductViewData;
use Litecommerce\Modules\Storefront\Contracts\ProductTabContext;
use Litecommerce\Modules\Storefront\Infrastructure\InMemoryProductTabRegistry;
use Tests\TestCase;

/**
 * Feature, not Unit, only because the class logs a failed renderer through
 * the Log facade (same as InMemoryStorefrontSlotRegistry), which needs the
 * app booted — no HTTP/DB involved, just Laravel's facade root.
 */
final class InMemoryProductTabRegistryTest extends TestCase
{
    private function context(): ProductTabContext
    {
        $product = new ProductViewData(1, 'demo', 'physical', 'canonical_to_product', 'Demo', null, null, null, [], [], [], [], [], []);

        return new ProductTabContext($product, [], []);
    }

    public function test_returns_registered_tabs_rendered_in_registration_order(): void
    {
        $registry = new InMemoryProductTabRegistry;

        $registry->register('first', 'plugin-a', 'Pierwsza', static fn (): string => '<p>A</p>');
        $registry->register('second', 'plugin-b', 'Druga', static fn (): string => '<p>B</p>');

        $tabs = $registry->all($this->context());

        $this->assertCount(2, $tabs);
        $this->assertSame('first', $tabs[0]->id);
        $this->assertSame('Pierwsza', $tabs[0]->label);
        $this->assertSame('<p>A</p>', $tabs[0]->html);
        $this->assertSame('second', $tabs[1]->id);
    }

    public function test_a_failing_tab_renderer_is_skipped_not_fatal(): void
    {
        $registry = new InMemoryProductTabRegistry;

        $registry->register('broken', 'plugin-a', 'Zepsuta', static function (): string {
            throw new \RuntimeException('boom');
        });
        $registry->register('fine', 'plugin-b', 'W porzadku', static fn (): string => '<p>OK</p>');

        $tabs = $registry->all($this->context());

        $this->assertCount(1, $tabs);
        $this->assertSame('fine', $tabs[0]->id);
    }

    public function test_no_registered_tabs_returns_an_empty_list(): void
    {
        $this->assertSame([], (new InMemoryProductTabRegistry)->all($this->context()));
    }

    public function test_the_render_closure_receives_the_product_tab_context(): void
    {
        $registry = new InMemoryProductTabRegistry;
        $context = $this->context();
        $seen = null;

        $registry->register('ctx', 'plugin-a', 'Context', function (ProductTabContext $received) use (&$seen): string {
            $seen = $received;

            return '<p>ok</p>';
        });

        $registry->all($context);

        $this->assertSame($context, $seen);
    }
}
