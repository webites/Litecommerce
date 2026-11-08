<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Application;

use App\Support\Events\CommittedEvent;
use App\Support\Versioning\VersionConstraint;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Extensions\Contracts\ExtensionCatalog;
use Litecommerce\Modules\Extensions\Contracts\ExtensionStatus;
use Litecommerce\Modules\Themes\Contracts\Events\ThemeActivated;
use Litecommerce\Modules\Themes\Contracts\ThemeStateException;
use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;
use Litecommerce\Modules\Themes\Infrastructure\ThemeAssetPublisher;

/**
 * "Jeden aktywny" (09-presentation.md) is enforced HERE, not by a unique
 * DB constraint — activating one theme always deactivates whichever other
 * theme was active, in the same transaction, so the two writes can never
 * be observed half-done.
 */
final class ActivateTheme
{
    public function __construct(
        private readonly ExtensionCatalog $extensions,
        private readonly ThemeAssetPublisher $assets,
        private readonly string $coreVersion,
    ) {}

    public function execute(string $themeId): void
    {
        $theme = Theme::query()->findOrFail($themeId);

        if ($theme->status === ThemeStatus::Active->value) {
            return;
        }

        $manifest = $theme->manifest;
        $coreConstraint = (string) ($manifest['core'] ?? '*');

        if (! VersionConstraint::satisfies($this->coreVersion, $coreConstraint)) {
            throw new ThemeStateException('core_incompatible', "Theme requires core version \"{$coreConstraint}\", installed core is {$this->coreVersion}.", ['constraint' => $coreConstraint, 'core_version' => $this->coreVersion]);
        }

        foreach ($manifest['dependencies'] ?? [] as $dependency) {
            $pluginId = (string) $dependency['id'];
            $pluginConstraint = (string) ($dependency['version'] ?? '*');
            $plugin = $this->extensions->find($pluginId);

            if ($plugin === null || $plugin->status !== ExtensionStatus::Enabled) {
                throw new ThemeStateException('required_plugin_unmet', "Theme requires plugin \"{$pluginId}\" to be installed and enabled.", ['plugin_id' => $pluginId]);
            }
            if (! VersionConstraint::satisfies($plugin->version, $pluginConstraint)) {
                throw new ThemeStateException('required_plugin_version_mismatch', "Plugin \"{$pluginId}\" version {$plugin->version} does not satisfy the requirement \"{$pluginConstraint}\".", ['plugin_id' => $pluginId, 'version' => $plugin->version, 'constraint' => $pluginConstraint]);
            }
        }

        DB::transaction(function () use ($theme, $themeId): void {
            Theme::query()->where('status', ThemeStatus::Active->value)->where('id', '!=', $themeId)
                ->update(['status' => ThemeStatus::Installed->value, 'activated_at' => null]);

            $theme->update(['status' => ThemeStatus::Active->value, 'activated_at' => now()]);
        });

        $this->assets->publish($themeId);

        // The generic hook a theme or plugin listens to from its own boot()
        // (Event::listen(ThemeActivated::class, ...)) — a plain fact, not a
        // hook that can block/alter this activation.
        DB::afterCommit(fn () => CommittedEvent::dispatch(new ThemeActivated($themeId)));
    }
}
