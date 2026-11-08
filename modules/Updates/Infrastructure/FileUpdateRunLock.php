<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Infrastructure;

use Litecommerce\Modules\Updates\Application\UpdateRunLock;

final class FileUpdateRunLock implements UpdateRunLock
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(): bool
    {
        $path = storage_path('app/updates/run.lock');

        if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            return false;
        }

        $handle = fopen($path, 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }

        $this->handle = null;
    }
}
