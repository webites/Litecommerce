<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure;

/**
 * A small PSR-4 autoloader independent of Composer's own — plugin code is
 * never present when `composer install` runs on the shop, so it cannot be
 * added to Composer's generated class map. Bound as a singleton; each
 * enabled plugin's namespace is added once during
 * Providers\ExtensionsServiceProvider::boot(), then registered with
 * spl_autoload_register a single time for the request.
 */
final class PluginAutoloader
{
    /** @var array<string, string> namespace prefix => absolute base directory */
    private array $namespaces = [];

    private bool $registered = false;

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $this->namespaces[$prefix] = rtrim($baseDir, '/');
        $this->ensureRegistered();
    }

    private function ensureRegistered(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        spl_autoload_register(function (string $class): void {
            foreach ($this->namespaces as $prefix => $baseDir) {
                if (! str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = substr($class, strlen($prefix));
                $file = $baseDir.'/'.str_replace('\\', '/', $relative).'.php';

                if (is_file($file)) {
                    require $file;

                    return;
                }
            }
        });
    }
}
