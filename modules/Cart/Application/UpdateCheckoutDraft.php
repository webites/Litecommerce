<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;

final class UpdateCheckoutDraft
{
    /**
     * @param  array{
     *     buyer_type: string,
     *     company_name: ?string,
     *     tax_identifier: ?string,
     *     registration_number: ?string,
     *     email: ?string,
     *     phone: ?string,
     *     notes: ?string,
     *     marketing_consent?: bool,
     *     billing_address: array<string, mixed>,
     *     ships_to_different_address: bool,
     *     shipping_address: ?array<string, mixed>,
     *     delivery_method_id: ?string,
     *     delivery_pickup_point_id: ?int,
     * }  $data
     * @param  list<int>  $ownedCartIds
     */
    public function execute(string $draftId, array $ownedCartIds, array $data): CheckoutDraft
    {
        return DB::transaction(function () use ($draftId, $ownedCartIds, $data): CheckoutDraft {
            $draft = CheckoutDraft::query()->whereIn('cart_id', $ownedCartIds)->lockForUpdate()->findOrFail($draftId);

            if ($draft->status !== 'open') {
                throw new CartValidationException('draft_confirmed', 'The checkout has already been confirmed and cannot be changed.');
            }

            // An account customer's email is theirs, not a free-text field
            // they can spoof through this endpoint — only a guest sets it.
            if ($draft->customer_account_id !== null) {
                unset($data['email']);
            }

            $draft->update([...$data, 'version' => $draft->version + 1]);

            return $draft->fresh();
        });
    }
}
