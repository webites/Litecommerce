<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure;

use InvalidArgumentException;
use Litecommerce\Modules\Email\Contracts\EmailTransport;
use Litecommerce\Modules\Email\Contracts\EmailTransportRegistry;

final class InMemoryEmailTransportRegistry implements EmailTransportRegistry
{
    /** @var array<string, EmailTransport> */
    private array $transports = [];

    /** @var array<string, string|null> */
    private array $owners = [];

    public function register(string $key, EmailTransport $transport, ?string $owner = null): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid email transport key [{$key}].");
        }
        if (isset($this->transports[$key])) {
            throw new InvalidArgumentException("Email transport [{$key}] is already registered.");
        }
        $this->transports[$key] = $transport;
        $this->owners[$key] = $owner;
    }

    public function owner(string $key): ?string
    {
        return $this->owners[$key] ?? null;
    }

    public function find(string $key): ?EmailTransport
    {
        return $this->transports[$key] ?? null;
    }

    public function keys(): array
    {
        return array_keys($this->transports);
    }
}
