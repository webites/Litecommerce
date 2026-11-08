<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Infrastructure;

interface DatabaseDumper
{
    /** Method name recorded in the manifest. */
    public function method(): string;

    /** Entry name inside the archive, e.g. database.sql. */
    public function entryName(): string;

    /** Writes a complete, restorable dump to $targetPath. */
    public function dumpTo(string $targetPath): void;
}
