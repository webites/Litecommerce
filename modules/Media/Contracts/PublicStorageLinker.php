<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Contracts;

/**
 * Makes the public disk reachable over HTTP. Shared hosting sometimes
 * disables symlink() — see docs/knowledge-base/10-operations.md#wymagania-środowiskowe.
 */
interface PublicStorageLinker
{
    public function ensureLinked(): void;
}
