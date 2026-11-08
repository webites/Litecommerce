<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure;

use Illuminate\Support\Facades\File;
use Litecommerce\Modules\Media\Contracts\PublicStorageLinker;
use Throwable;

final class FilesystemPublicStorageLinker implements PublicStorageLinker
{
    public function ensureLinked(): void
    {
        $link = public_path('storage');

        if (file_exists($link) || is_link($link)) {
            return;
        }

        $target = storage_path('app/public');
        File::ensureDirectoryExists($target);

        if (function_exists('symlink')) {
            try {
                symlink($target, $link);

                return;
            } catch (Throwable) {
                // Some shared hosts disable symlink() entirely — fall
                // back to a one-time copy below.
            }
        }

        File::copyDirectory($target, $link);
    }
}
