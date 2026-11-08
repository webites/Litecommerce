<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Redirects storage_path() to a temp directory so tests touching
 * InstallationLock/session files never write into the real repo's storage/.
 * Also repoints the local/public filesystem disks (their root is normally
 * baked in from storage_path() at config load time, before this runs) so
 * Media tests never write into the real repo's storage/app/ either.
 */
trait UsesIsolatedStorage
{
    protected function useIsolatedStorage(): string
    {
        $path = sys_get_temp_dir().'/litecommerce-test-storage-'.uniqid();

        foreach (['framework/sessions', 'framework/views', 'framework/cache/data', 'logs', 'app/public', 'app/private'] as $subdir) {
            mkdir($path.'/'.$subdir, 0777, true);
        }

        $this->app->useStoragePath($path);
        config(['filesystems.disks.local.root' => $path.'/app/private']);
        config(['filesystems.disks.public.root' => $path.'/app/public']);
        config(['cache.stores.file.path' => $path.'/framework/cache/data']);

        return $path;
    }
}
