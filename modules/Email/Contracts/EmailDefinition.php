<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class EmailDefinition
{
    /**
     * @param  list<EmailVariableDefinition>  $variables
     * @param  array<string, array{subject:string,html:string}>  $defaults
     * @param  array<string, mixed>  $sampleVariables
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $variables,
        public array $defaults,
        public array $sampleVariables,
        public bool $mandatory = false,
        public bool $defaultEnabled = true,
        public int $priority = 50,
        public ?string $owner = null,
    ) {}
}
