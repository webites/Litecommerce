<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface SanitizableHtmlContent
{
    public function label(): string;

    /** Rewrites stored HTML through the given sanitizer and returns the number of changed records. */
    public function sanitizeStored(HtmlContentFilter $filter): int;
}
