<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Contracts;

/**
 * Unlike MediaData, deliberately carries no path/url — a private file is
 * never linked to directly. Callers that need to hand it to a browser go
 * through PrivateFileLibrary::respond().
 */
final readonly class PrivateFileData
{
    public function __construct(
        public int $id,
        public string $originalFilename,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksumSha256,
        public string $integrityStatus = 'ok',
    ) {}
}
