<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

final readonly class ConsumeDownloadResult
{
    public function __construct(
        public DownloadOutcome $outcome,
        public ?int $privateFileId = null,
        public ?string $downloadFilename = null,
    ) {}
}
