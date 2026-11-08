<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

use Illuminate\Contracts\Foundation\Application;

/**
 * Optional, checked with instanceof. A plugin that owns long-lived obligations (open payment
 * attempts, issued rights) implements it so disabling does not strand them: while
 * hasOutstandingObligations() is true the extension is "winding_down" and windDown() runs on
 * every request instead of register()/boot(). windDown() must register only the handlers that
 * settle existing obligations and must refuse new purchases.
 */
interface PluginWindDownHook
{
    public function hasOutstandingObligations(Application $app): bool;

    public function windDown(Application $app): void;
}
