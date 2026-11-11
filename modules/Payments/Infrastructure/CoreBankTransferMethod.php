<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure;

use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentAvailabilityContext;
use Litecommerce\Modules\Payments\Contracts\PaymentContext;
use Litecommerce\Modules\Payments\Contracts\PaymentMethod;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodDescriptor;
use Litecommerce\Modules\Payments\Contracts\PaymentStartResult;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Litecommerce\Modules\Payments\Contracts\VerifiedPaymentNotification;
use Litecommerce\Modules\Payments\Infrastructure\Models\BankTransferAccount;
use LogicException;

final class CoreBankTransferMethod implements PaymentMethod
{
    public function descriptor(): PaymentMethodDescriptor
    {
        return new PaymentMethodDescriptor('core.bank_transfer', 'core', trans('payments::methods.bank_transfer'), 'bank_transfer');
    }

    public function available(PaymentAvailabilityContext $context): bool
    {
        return ! $context->isFullyCovered()
            && BankTransferAccount::query()->whereKey($context->currencyCode)->where('enabled', true)->exists();
    }

    public function start(PaymentContext $context): PaymentStartResult
    {
        $account = BankTransferAccount::query()->whereKey($context->currencyCode)->where('enabled', true)->firstOrFail();
        $instructions = $account->instructions ?? [];

        return new PaymentStartResult('bank-transfer:'.$context->orderId, PaymentAttemptStatus::Pending, metadata: [
            'beneficiary_name' => $account->beneficiary_name,
            'account_number' => $account->account_number,
            'transfer_title' => $context->orderNumber,
            'instructions' => $instructions[$context->locale] ?? $instructions,
        ]);
    }

    public function verifyNotification(string $rawBody, array $headers): VerifiedPaymentNotification
    {
        throw new LogicException('A bank transfer is confirmed manually and has no webhook.');
    }

    public function query(string $providerTransactionId): PaymentStatusResult
    {
        return new PaymentStatusResult(PaymentAttemptStatus::Pending);
    }

    public function cancel(string $providerTransactionId): void {}
}
