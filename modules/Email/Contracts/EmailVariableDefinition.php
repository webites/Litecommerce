<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class EmailVariableDefinition
{
    public function __construct(
        public string $key,
        public EmailVariableType $type,
        public string $label,
        public bool $required = true,
    ) {}
}
