<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface EmailMessageCatalog
{
    /** @return list<EmailMessageSummary> */
    public function forSource(string $sourceType, string $sourceId, ?string $definitionKey = null): array;
}
