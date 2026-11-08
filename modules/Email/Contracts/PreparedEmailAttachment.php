<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class PreparedEmailAttachment
{
    public function __construct(
        public string $filename,
        public string $mimeType,
        public string $disk,
        public string $path,
        public int $sizeBytes,
        public string $checksumSha256,
    ) {}
}
