<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

/**
 * Optional — implement alongside PluginServiceProvider when the plugin
 * needs to seed data or otherwise act once, right after its own migrations
 * run. Called exactly once per install, never on enable/disable.
 */
interface PluginInstallHook
{
    public function install(): void;
}
