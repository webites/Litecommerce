<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

final readonly class SettingsTabContent
{
    /** @param array<string, mixed> $props */
    private function __construct(
        public ?string $component,
        public array $props,
        public ?string $html,
    ) {}

    /** @param array<string, mixed> $props */
    public static function component(string $component, array $props): self
    {
        return new self($component, $props, null);
    }

    public static function html(string $html): self
    {
        return new self(null, [], $html);
    }
}
