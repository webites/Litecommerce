<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Litecommerce\Modules\Cms\Contracts\MenuItemType;

/**
 * Validates the whole item tree's shape before Cms\Application\SaveMenuItems
 * ever writes it as one JSON blob — the one real cost of not using a
 * normalized table with per-type database constraints (see the "menus"
 * migration). Depth is unbounded, so this walks the tree manually in
 * withValidator() rather than declaring fixed-depth "tree.*.children.*..."
 * rules, which can only express a fixed number of nesting levels.
 */
final class SaveMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'tree' => ['present', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateNodes((array) $this->input('tree'), $validator, 'tree');
        });
    }

    /** @return list<array<string, mixed>> */
    public function tree(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->validated('tree');
    }

    private function validateNodes(array $nodes, Validator $validator, string $path): void
    {
        foreach ($nodes as $index => $node) {
            $this->validateNode(is_array($node) ? $node : [], $validator, "{$path}.{$index}");
        }
    }

    /** @param  array<string, mixed>  $node */
    private function validateNode(array $node, Validator $validator, string $path): void
    {
        $type = MenuItemType::tryFrom(is_string($node['type'] ?? null) ? $node['type'] : '');

        if ($type === null) {
            $validator->errors()->add("{$path}.type", trans('cms::validation.menu_item_type'));

            return;
        }

        if (! is_string($node['id'] ?? null) || $node['id'] === '') {
            $validator->errors()->add("{$path}.id", trans('cms::validation.menu_item_id'));
        }

        match ($type) {
            MenuItemType::Page, MenuItemType::Archetype => is_scalar($node['referenceId'] ?? null)
                ? null
                : $validator->errors()->add("{$path}.referenceId", trans('cms::validation.menu_item_instance')),
            MenuItemType::Widget => is_string($node['referenceKey'] ?? null) && $node['referenceKey'] !== ''
                ? null
                : $validator->errors()->add("{$path}.referenceKey", trans('cms::validation.menu_item_widget')),
            MenuItemType::Custom => is_string($node['url'] ?? null) && $node['url'] !== ''
                ? null
                : $validator->errors()->add("{$path}.url", trans('cms::validation.menu_item_url')),
        };

        if ($type === MenuItemType::Archetype && (! is_string($node['referenceKey'] ?? null) || $node['referenceKey'] === '')) {
            $validator->errors()->add("{$path}.referenceKey", trans('cms::validation.menu_item_archetype'));
        }

        if (isset($node['children'])) {
            if (! is_array($node['children'])) {
                $validator->errors()->add("{$path}.children", trans('cms::validation.menu_item_children'));

                return;
            }

            $this->validateNodes($node['children'], $validator, "{$path}.children");
        }
    }
}
