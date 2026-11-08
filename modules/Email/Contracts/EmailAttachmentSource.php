<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface EmailAttachmentSource
{
    public function filename(): string;

    public function mimeType(): string;

    public function sizeBytes(): int;

    /** @return resource */
    public function openStream();
}
