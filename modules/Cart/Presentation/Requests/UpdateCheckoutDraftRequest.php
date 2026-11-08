<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Identity\Contracts\BuyerProfileType;

final class UpdateCheckoutDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $address = fn (string $prefix, bool $required): array => [
            "{$prefix}.recipient_name" => [$required ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.company_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.country_code" => [$required ? 'required' : 'nullable', 'string', 'size:2', Rule::in(array_keys(config('delivery.continents', [])))],
            "{$prefix}.postal_code" => [$required ? 'required' : 'nullable', 'string', 'max:20'],
            "{$prefix}.city" => [$required ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.region" => ['nullable', 'string', 'max:255'],
            "{$prefix}.address_line_1" => [$required ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.address_line_2" => ['nullable', 'string', 'max:255'],
            "{$prefix}.phone" => ['nullable', 'string', 'max:32'],
        ];

        return [
            'buyer_type' => ['required', Rule::enum(BuyerProfileType::class)],
            'company_name' => ['required_if:buyer_type,company', 'nullable', 'string', 'max:255'],
            'tax_identifier' => ['nullable', 'string', 'max:64'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'email' => [$this->isGuest() ? 'required' : 'sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'marketing_consent' => ['sometimes', 'boolean'],
            'billing_address' => ['required', 'array'],
            ...$address('billing_address', true),
            'ships_to_different_address' => ['required', 'boolean'],
            'shipping_address' => ['required_if:ships_to_different_address,true', 'nullable', 'array'],
            ...$address('shipping_address', $this->boolean('ships_to_different_address')),
            // Existence, availability and pickup-point ownership are
            // re-checked live against current Delivery/address state at
            // pricing and confirm time — never trusted from here alone,
            // the same way payment_method_id is only validated at confirm.
            'delivery_method_id' => ['nullable', 'string', 'max:191'],
            'delivery_pickup_point_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedData(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $data['shipping_address'] = ($data['ships_to_different_address'] ?? false) ? ($data['shipping_address'] ?? null) : null;

        if ($this->isGuest()) {
            return $data;
        }

        unset($data['email']);

        return $data;
    }

    private function isGuest(): bool
    {
        return ! auth('customer')->check();
    }
}
