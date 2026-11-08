<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Requests;

use Illuminate\Validation\Rule;
use Litecommerce\Modules\Catalog\Domain\ValidGtin;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;

final class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $product = $this->route('product');
        $variantId = $product instanceof Product
            ? $product->variants()->where('is_default', true)->value('id')
            : null;

        $rules['sku'] = [
            'nullable', 'string', 'max:96', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
            Rule::unique('product_variants', 'sku')->ignore($variantId),
        ];
        $rules['gtin'] = [
            'nullable', 'string', new ValidGtin,
            Rule::unique('product_variants', 'gtin')->ignore($variantId),
        ];

        return $rules;
    }
}
