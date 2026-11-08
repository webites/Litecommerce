<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

final readonly class PaymentAttemptData
{
    /**
     * @param  array<string, mixed>  $metadata  Method-specific data a
     *                                          storefront confirmation page
     *                                          needs to show the customer —
     *                                          e.g. bank transfer's
     *                                          beneficiary_name/account_number/instructions
     *                                          (Infrastructure\CoreBankTransferMethod). Excludes
     *                                          "redirect_url", which has its own field.
     */
    public function __construct(
        public string $id,
        public string $orderId,
        public string $providerId,
        public string $methodId,
        public ?string $providerTransactionId,
        public PaymentAttemptStatus $status,
        public string $amount,
        public string $currencyCode,
        public ?string $redirectUrl = null,
        public array $metadata = [],
    ) {}
}
