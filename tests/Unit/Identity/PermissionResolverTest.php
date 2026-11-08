<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use InvalidArgumentException;
use Litecommerce\Modules\Identity\Application\PermissionResolver;
use Litecommerce\Modules\Identity\Contracts\PermissionDefinition;
use Litecommerce\Modules\Identity\Infrastructure\Permissions\InMemoryPermissionRegistry;
use PHPUnit\Framework\TestCase;

final class PermissionResolverTest extends TestCase
{
    public function test_explicit_false_overrides_a_role_grant(): void
    {
        $resolver = $this->resolverWith('vendor.catalog.edit', ['administrator']);

        self::assertFalse($resolver->allows(
            'administrator',
            ['vendor.catalog.edit' => false],
            'vendor.catalog.edit',
        ));
    }

    public function test_explicit_true_grants_permission_missing_from_role(): void
    {
        $resolver = $this->resolverWith('vendor.invoices.edit', ['administrator']);

        self::assertTrue($resolver->allows(
            'warehouse_worker',
            ['vendor.invoices.edit' => true],
            'vendor.invoices.edit',
        ));
    }

    public function test_missing_override_inherits_role_grant(): void
    {
        $resolver = $this->resolverWith('vendor.orders.view', ['warehouse_worker']);

        self::assertTrue($resolver->allows('warehouse_worker', [], 'vendor.orders.view'));
        self::assertFalse($resolver->allows('content_editor', [], 'vendor.orders.view'));
    }

    public function test_unknown_permission_is_denied_even_with_privileged_role(): void
    {
        $resolver = $this->resolverWith('vendor.orders.view', ['administrator']);

        self::assertFalse($resolver->allows('administrator', [], 'missing.permission'));
    }

    public function test_registry_requires_namespaced_unique_keys(): void
    {
        $registry = new InMemoryPermissionRegistry;
        $definition = new PermissionDefinition('acme.invoice.edit', 'Edycja faktur', 'Faktury');
        $registry->register($definition);

        self::assertSame($definition, $registry->find('acme.invoice.edit'));

        $this->expectException(InvalidArgumentException::class);
        $registry->register($definition);
    }

    /** @param list<string> $roles */
    private function resolverWith(string $key, array $roles): PermissionResolver
    {
        $registry = new InMemoryPermissionRegistry;
        $registry->register(new PermissionDefinition($key, 'Test', 'Test', $roles));

        return new PermissionResolver($registry);
    }
}
