<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Application;

use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Email\Contracts\EmailTransportObligationChecker;
use Litecommerce\Modules\Extensions\Contracts\ExtensionStateException;
use Litecommerce\Modules\Extensions\Contracts\PluginUninstallHook;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use Litecommerce\Modules\Extensions\Infrastructure\PluginProviderResolver;

final class UninstallExtension
{
    public function __construct(
        private readonly PluginProviderResolver $providers,
        private readonly EmailTransportObligationChecker $emailObligations,
        private readonly string $disk,
        private readonly string $basePath,
    ) {}

    public function execute(string $extensionId): void
    {
        $extension = Extension::query()->findOrFail($extensionId);

        if ($extension->status === 'enabled') {
            throw new ExtensionStateException('still_enabled', 'Wyłącz rozszerzenie przed odinstalowaniem.');
        }

        if ($extension->status === 'winding_down') {
            throw new ExtensionStateException('still_winding_down', 'Rozszerzenie wygasza jeszcze swoje zobowiązania. Odinstaluj je po zakończeniu wygaszania.');
        }

        $dependents = Extension::query()->where('id', '!=', $extensionId)->get()
            ->filter(function (Extension $candidate) use ($extensionId): bool {
                $dependencyIds = array_map(static fn (array $dependency): string => (string) $dependency['id'], $candidate->manifest['dependencies'] ?? []);

                return in_array($extensionId, $dependencyIds, true);
            });

        if ($dependents->isNotEmpty()) {
            $names = $dependents->pluck('name')->implode(', ');
            throw new ExtensionStateException('required_by_dependents', "Inne zainstalowane rozszerzenia zależą od tego: {$names}. Odinstaluj je najpierw.");
        }

        if ($this->emailObligations->hasOutstandingForOwner($extensionId)) {
            throw new ExtensionStateException('email_transport_in_use', 'Transport e-mail tego rozszerzenia ma niezakończone wiadomości. Zakończ je albo jawnie anuluj przed odinstalowaniem.');
        }

        if ($extension->installed_at !== null) {
            $provider = $this->providers->resolve($extension);
            if ($provider instanceof PluginUninstallHook) {
                $provider->uninstall();
            }
        }

        Storage::disk($this->disk)->deleteDirectory($this->basePath.'/'.$extensionId);
        // Cascades to extension_settings via the FK.
        $extension->delete();
    }
}
