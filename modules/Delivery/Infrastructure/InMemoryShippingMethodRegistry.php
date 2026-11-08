<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure;

use InvalidArgumentException;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodDescriptor;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodRegistry;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodSource;

final class InMemoryShippingMethodRegistry implements ShippingMethodRegistry
{
    /** @var array<string, ShippingMethod> */
    private array $methods = [];

    /** @var list<ShippingMethodSource> */
    private array $sources = [];

    public function register(ShippingMethod $method): void
    {
        $descriptor = $method->descriptor();

        if ($descriptor->providerId === ConfiguredShippingMethod::PROVIDER) {
            throw new InvalidArgumentException('The provider id ['.ConfiguredShippingMethod::PROVIDER.'] is reserved for configured delivery methods.');
        }

        if (! str_starts_with($descriptor->id, $descriptor->providerId.'.')) {
            throw new InvalidArgumentException("Shipping method id [{$descriptor->id}] must start with [{$descriptor->providerId}.].");
        }

        if (isset($this->methods[$descriptor->id])) {
            throw new InvalidArgumentException("Shipping method [{$descriptor->id}] is already registered.");
        }

        $this->methods[$descriptor->id] = $method;
    }

    public function addSource(ShippingMethodSource $source): void
    {
        $this->sources[] = $source;
    }

    public function has(string $methodId): bool
    {
        return $this->find($methodId) !== null;
    }

    public function get(string $methodId): ShippingMethod
    {
        return $this->find($methodId) ?? throw new InvalidArgumentException("Unknown shipping method [{$methodId}].");
    }

    public function methods(): array
    {
        $methods = [];

        foreach ($this->sources as $source) {
            array_push($methods, ...$source->methods());
        }

        return [...$methods, ...array_values($this->methods)];
    }

    public function all(): array
    {
        return array_map(static fn (ShippingMethod $method): ShippingMethodDescriptor => $method->descriptor(), $this->methods());
    }

    private function find(string $methodId): ?ShippingMethod
    {
        if (isset($this->methods[$methodId])) {
            return $this->methods[$methodId];
        }

        foreach ($this->sources as $source) {
            $method = $source->find($methodId);

            if ($method !== null) {
                return $method;
            }
        }

        return null;
    }
}
