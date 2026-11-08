<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use InvalidArgumentException;
use Litecommerce\Modules\Delivery\Contracts\DeliveryQuoteContext;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodDescriptor;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodQuote;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodSource;
use Litecommerce\Modules\Delivery\Infrastructure\InMemoryShippingMethodRegistry;
use Tests\TestCase;

/** P2-002: the Delivery SDK's "critical hook" shape, mirrored on PaymentMethodRegistry. */
final class ShippingMethodRegistryTest extends TestCase
{
    private function method(string $id, string $provider = 'test-plugin'): ShippingMethod
    {
        return new class($id, $provider) implements ShippingMethod
        {
            public function __construct(private readonly string $id, private readonly string $provider) {}

            public function descriptor(): ShippingMethodDescriptor
            {
                return new ShippingMethodDescriptor($this->id, $this->provider, 'Test method');
            }

            public function available(DeliveryQuoteContext $context): bool
            {
                return true;
            }

            public function quote(DeliveryQuoteContext $context, string $locale): ?ShippingMethodQuote
            {
                return new ShippingMethodQuote('9.99');
            }
        };
    }

    public function test_register_get_and_all_round_trip(): void
    {
        $registry = new InMemoryShippingMethodRegistry;
        $registry->register($this->method('test-plugin.flat'));

        $this->assertSame('test-plugin.flat', $registry->get('test-plugin.flat')->descriptor()->id);
        $this->assertCount(1, $registry->all());
        $this->assertSame('test-plugin.flat', $registry->all()[0]->id);
    }

    public function test_registering_the_same_id_twice_is_rejected(): void
    {
        $registry = new InMemoryShippingMethodRegistry;
        $registry->register($this->method('test-plugin.flat'));

        $this->expectException(InvalidArgumentException::class);
        $registry->register($this->method('test-plugin.flat'));
    }

    public function test_an_unknown_method_id_is_rejected(): void
    {
        $registry = new InMemoryShippingMethodRegistry;

        $this->expectException(InvalidArgumentException::class);
        $registry->get('unknown');
    }

    public function test_the_core_provider_is_reserved(): void
    {
        $registry = new InMemoryShippingMethodRegistry;

        $this->expectException(InvalidArgumentException::class);
        $registry->register($this->method('core.1', 'core'));
    }

    public function test_an_id_must_start_with_its_provider_prefix(): void
    {
        $registry = new InMemoryShippingMethodRegistry;

        $this->expectException(InvalidArgumentException::class);
        $registry->register($this->method('flat', 'test-plugin'));
    }

    public function test_a_source_supplies_methods_next_to_registered_ones(): void
    {
        $registry = new InMemoryShippingMethodRegistry;
        $registry->register($this->method('test-plugin.flat'));
        $registry->addSource(new class($this->method('dynamic.one', 'dynamic')) implements ShippingMethodSource
        {
            public function __construct(private readonly ShippingMethod $method) {}

            public function methods(): array
            {
                return [$this->method];
            }

            public function find(string $methodId): ?ShippingMethod
            {
                return $methodId === 'dynamic.one' ? $this->method : null;
            }
        });

        $this->assertTrue($registry->has('dynamic.one'));
        $this->assertFalse($registry->has('dynamic.two'));
        $this->assertSame(['dynamic.one', 'test-plugin.flat'], array_map(static fn ($descriptor): string => $descriptor->id, $registry->all()));
    }
}
