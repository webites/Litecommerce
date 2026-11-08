<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

/**
 * Optional — implement alongside PluginServiceProvider when the plugin
 * must decide the fate of its own data before its record and files are
 * removed ("Autor decyduje o danych podczas uninstall", 08-extensions.md).
 * Only ever called on an already-Disabled extension.
 */
interface PluginUninstallHook
{
    public function uninstall(): void;
}
