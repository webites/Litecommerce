<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Extensions\Contracts\ExtensionInstallationException;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;

// Turns an Extension row into a loadable object: registers its PSR-4 namespaces, then instantiates its manifest "provider" class.
final class PluginProviderResolver
{
    public function __construct(
        private readonly PluginAutoloader $autoloader,
        private readonly string $disk,
        private readonly string $basePath,
    ) {}

    public function packagePath(string $extensionId): string
    {
        return Storage::disk($this->disk)->path($this->basePath.'/'.$extensionId);
    }

    public function resolve(Extension $extension): object
    {
        $packagePath = $this->packagePath($extension->id);
        $autoload = $extension->manifest['autoload']['psr-4'] ?? [];

        foreach ($autoload as $prefix => $relativeDir) {
            $this->autoloader->addNamespace((string) $prefix, $packagePath.'/'.ltrim((string) $relativeDir, '/'));
        }

        // Registered here, not just in the enabled-extensions boot loop, so install()/uninstall() hooks
        // (which run through this same method while a plugin may still be disabled) can also use trans().
        // Own namespace = own id, so it can never collide with another plugin's or a core module's.
        $langPath = $packagePath.'/lang';
        if (is_dir($langPath)) {
            Lang::addNamespace($extension->id, $langPath);
        }

        $providerClass = $extension->manifest['provider'] ?? null;
        if (! is_string($providerClass) || $providerClass === '' || ! class_exists($providerClass)) {
            throw new ExtensionInstallationException('provider_class_not_found', "Provider class \"{$providerClass}\" does not exist.", ['class' => (string) $providerClass]);
        }

        return new $providerClass;
    }
}
