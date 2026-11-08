<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\SanitizableHtmlContent;

final readonly class SanitizeStoredHtml
{
    /** @param  iterable<SanitizableHtmlContent>  $targets */
    public function __construct(private HtmlContentFilter $filter, private iterable $targets) {}

    /** @return array<string, int> number of rewritten records per content area */
    public function handle(): array
    {
        $result = [];
        foreach ($this->targets as $target) {
            $result[$target->label()] = $target->sanitizeStored($this->filter);
        }

        return $result;
    }
}
