<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface EmailDefinitionRegistry
{
    public function register(EmailDefinition $definition): void;

    public function find(string $key): ?EmailDefinition;

    /** @return list<EmailDefinition> */
    public function all(): array;
}
