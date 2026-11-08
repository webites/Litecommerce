<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Application;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Extensions\Contracts\ExtensionStateException;
use Litecommerce\Modules\Extensions\Contracts\PluginWindDownHook;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use Litecommerce\Modules\Extensions\Infrastructure\PluginProviderResolver;

/**
 * "Nie można wyłączyć pluginu wymaganego przez aktywny motyw/plugin"
 * (08-extensions.md) — only checks other *enabled* extensions, since a
 * disabled dependent could never have been enabled itself without this
 * one already being enabled (EnableExtension re-checks dependencies too).
 * Stops new hook dispatch only; existing data/obligations are the
 * plugin's own contracts' responsibility, not something this framework
 * can enforce for arbitrary third-party code.
 */
final class DisableExtension
{
    public function __construct(private readonly PluginProviderResolver $providers) {}

    public function execute(string $extensionId): void
    {
        $extension = Extension::query()->findOrFail($extensionId);

        if (in_array($extension->status, ['disabled', 'winding_down'], true)) {
            return;
        }

        $dependents = Extension::query()->where('status', 'enabled')->where('id', '!=', $extensionId)->get()
            ->filter(function (Extension $candidate) use ($extensionId): bool {
                $dependencyIds = array_map(static fn (array $dependency): string => (string) $dependency['id'], $candidate->manifest['dependencies'] ?? []);

                return in_array($extensionId, $dependencyIds, true);
            });

        if ($dependents->isNotEmpty()) {
            $names = $dependents->pluck('name')->implode(', ');
            throw new ExtensionStateException('required_by_dependents', "Rozszerzenie jest wymagane przez włączone: {$names}. Wyłącz je najpierw.");
        }

        $extension->update(['status' => $this->hasOutstandingObligations($extension) ? 'winding_down' : 'disabled', 'enabled_at' => null]);
    }

    private function hasOutstandingObligations(Extension $extension): bool
    {
        try {
            $provider = $this->providers->resolve($extension);

            return $provider instanceof PluginWindDownHook && $provider->hasOutstandingObligations(app());
        } catch (\Throwable $exception) {
            Log::warning("Could not check wind-down obligations of extension [{$extension->id}].", ['exception' => $exception]);

            return false;
        }
    }
}
