<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure;

use InvalidArgumentException;
use Litecommerce\Modules\Email\Contracts\EmailDefinition;
use Litecommerce\Modules\Email\Contracts\EmailDefinitionRegistry;

final class InMemoryEmailDefinitionRegistry implements EmailDefinitionRegistry
{
    /** @var array<string, EmailDefinition> */
    private array $definitions = [];

    public function register(EmailDefinition $definition): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,119}$/', $definition->key) !== 1) {
            throw new InvalidArgumentException("Invalid email definition key [{$definition->key}].");
        }
        if (isset($this->definitions[$definition->key])) {
            throw new InvalidArgumentException("Email definition [{$definition->key}] is already registered.");
        }

        $keys = [];
        foreach ($definition->variables as $variable) {
            if (preg_match('/^[a-z][a-z0-9_.-]{0,79}$/', $variable->key) !== 1 || isset($keys[$variable->key])) {
                throw new InvalidArgumentException("Invalid or duplicate email variable [{$variable->key}].");
            }
            $keys[$variable->key] = true;
        }
        if ($definition->defaults === []) {
            throw new InvalidArgumentException("Email definition [{$definition->key}] requires a default template.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function find(string $key): ?EmailDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function all(): array
    {
        return array_values($this->definitions);
    }
}
