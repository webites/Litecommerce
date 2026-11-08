<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

use Illuminate\Contracts\Foundation\Application;

/**
 * The bootstrap class every plugin manifest names in `provider` — the same
 * register()/boot() split as a native Laravel ServiceProvider, but a plain
 * interface rather than the base class so a plugin never needs Laravel's
 * ServiceProvider internals in its own autoloaded namespace.
 *
 * Called on every request while the plugin is Enabled (see
 * Providers\ExtensionsServiceProvider) — register() binds services,
 * boot() is the place to call into other modules' extension points
 * (e.g. Payments\Contracts\PaymentMethodRegistry::register(),
 * PanelSlotRegistry::register()).
 *
 * A plugin that also needs one-time install/uninstall work implements
 * PluginInstallHook/PluginUninstallHook in addition to this interface —
 * checked with instanceof, never called unconditionally, so most plugins
 * never need empty no-op methods.
 */
interface PluginServiceProvider
{
    public function register(Application $app): void;

    public function boot(Application $app): void;
}
