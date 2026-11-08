<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

/**
 * The panel's extension point for plugin-rendered fragments — see "Rozruch
 * i rozszerzalność" (08-extensions.md) for why this exists instead of a
 * Blade-override mechanism: the panel is Inertia/React, so a plugin cannot
 * inject a component into the bundle. Instead it registers a server-side
 * renderer for a named slot; the panel fetches rendered fragments over
 * GET /panel/extensions/slots/{slot} and mounts them as HTML — not
 * further sanitized here, since plugin PHP is already trusted code with
 * full framework access (see "SDK i zależności", 08-extensions.md); the
 * plugin author is responsible for safely escaping any external data its
 * own fragment renders, same as for the rest of its code.
 *
 * A fresh registry per request — renderers are (re-)registered by every
 * Enabled plugin's boot() call, same lifetime as the request itself.
 */
interface PanelSlotRegistry
{
    /**
     * @param  \Closure(array<string, string>): string  $renderer  Returns an HTML fragment; receives the slot's
     *                                                             render-time context (e.g. {order_id: "..."} for a per-entity slot like "orders.show.documents") —
     *                                                             a renderer that doesn't need it can simply declare a zero-argument closure, PHP ignores the extra
     *                                                             argument. Exceptions are caught and logged, never break the page (this is an optional-hook slot,
     *                                                             not a critical one — see "Hooki" in 08-extensions.md).
     */
    public function register(string $slot, string $extensionId, \Closure $renderer): void;

    /**
     * @param  array<string, string>  $context  Caller-supplied render-time data (e.g. the current order id on
     *                                          a per-order slot) — opaque to the registry itself, passed through verbatim to each renderer.
     * @return list<array{extension_id: string, html: string}>
     */
    public function render(string $slot, array $context = []): array;
}
