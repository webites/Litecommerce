<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/**
 * Storefront's equivalent of Extensions\Contracts\PanelSlotRegistry, with
 * one difference: rendered synchronously, server-side, during the same
 * request that renders the page — the storefront is Blade-first (unlike
 * the Inertia/React panel), so there is no second JSON round-trip to fetch
 * fragments after the page loads. A plugin registers a callback from its
 * own PluginServiceProvider::boot(), exactly as it would for a panel slot.
 */
interface StorefrontSlotRegistry
{
    /** @param \Closure(): string $render Returns an HTML fragment; exceptions are caught and logged, never break the page — same optional-hook contract as PanelSlotRegistry. */
    public function register(string $slot, string $extensionId, \Closure $render): void;

    /** Concatenated HTML of every fragment registered for this slot, in registration order. Never sanitized further — see PanelSlotRegistry for why registering trusted plugin PHP's own output needs no extra sanitization. */
    public function render(string $slot): string;
}
