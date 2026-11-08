<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface EmailTransportRegistry
{
    public function register(string $key, EmailTransport $transport, ?string $owner = null): void;

    public function find(string $key): ?EmailTransport;

    public function owner(string $key): ?string;

    /** @return list<string> */
    public function keys(): array;
}
