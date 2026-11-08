<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

use Illuminate\Support\Str;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessGrantor;
use Litecommerce\Modules\Digital\Infrastructure\DigitalDataMapper;
use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadGrant;
use Litecommerce\Modules\Digital\Infrastructure\Models\VariantFileSet;

final class GrantDigitalAccess implements DigitalAccessGrantor
{
    public function grantForOrderLine(
        int $variantId,
        string $referenceType,
        string $referenceId,
        ?string $orderId,
        ?int $customerAccountId,
        string $email,
        \DateTimeImmutable $grantedAt,
    ): array {
        $fileSets = VariantFileSet::query()->where('variant_id', $variantId)
            ->where('active', true)->whereNotNull('current_version_id')
            ->orderBy('position')->orderBy('id')->get();

        $grants = [];

        foreach ($fileSets as $fileSet) {
            $expiresAt = $fileSet->validity_days === null ? null : $grantedAt->modify("+{$fileSet->validity_days} days");

            // firstOrCreate keys on the same unique constraint that backs
            // idempotency here — a retried confirmation (webhook replay,
            // ManageOrderPayment already-confirmed short-circuit lifted)
            // never doubles a grant for the same purchased line/file.
            $grant = DownloadGrant::query()->firstOrCreate(
                [
                    'reference_type' => $referenceType, 'reference_id' => $referenceId,
                    'variant_file_set_id' => $fileSet->getKey(),
                ],
                [
                    'access_token' => $this->generateToken(),
                    'digital_asset_version_id' => $fileSet->current_version_id,
                    'order_id' => $orderId, 'customer_account_id' => $customerAccountId, 'email' => $email,
                    'granted_at' => $grantedAt, 'expires_at' => $expiresAt,
                    'max_downloads' => $fileSet->max_downloads, 'downloads_used' => 0,
                ],
            );
            $grants[] = DigitalDataMapper::toGrantData($grant->setRelation('fileSet', $fileSet)->load('version'));
        }

        return $grants;
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (DownloadGrant::query()->where('access_token', $token)->exists());

        return $token;
    }
}
