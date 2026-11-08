<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Litecommerce\Modules\Digital\Contracts\DigitalGrantData;
use Litecommerce\Modules\Orders\Contracts\OrderDetailData;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptData;

/**
 * Used both for the post-checkout "thank you" page and for a customer
 * account's own order detail page — same shape either way, see
 * "Kontrakty widoków" (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
 */
final readonly class OrderConfirmationPageData
{
    /** @param list<DigitalGrantData> $digitalGrants only ever usable ones */
    public function __construct(
        public OrderDetailData $order,
        public ?PaymentAttemptData $latestPayment,
        public array $digitalGrants,
    ) {}
}
