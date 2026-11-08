<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

interface BlockDataRegistry
{
    /**
     * @param  \Closure(array<string, mixed>, string, array<string, mixed>): array<string, mixed>  $resolver
     */
    public function register(string $type, \Closure $resolver): void;

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function resolve(string $type, array $props, string $locale, array $context): array;
}
