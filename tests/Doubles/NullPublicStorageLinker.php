<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Litecommerce\Modules\Media\Contracts\PublicStorageLinker;

/**
 * Installer tests never isolate public_path(), only storage_path() — a
 * real linker would symlink/copy into the developer's actual public/
 * directory. Finalize only needs the step to run, not its filesystem effect.
 */
final class NullPublicStorageLinker implements PublicStorageLinker
{
    public function ensureLinked(): void {}
}
