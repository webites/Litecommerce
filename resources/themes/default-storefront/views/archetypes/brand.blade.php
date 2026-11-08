{{--
    Theme's default block-based template for the 'brand' archetype — see
    archetypes/category.blade.php's docblock for the resolution mechanism.
--}}
{!! json_encode([
    'content' => [
        ['type' => 'archive-heading', 'props' => ['show_description' => '1', 'width' => 'contained']],
        ['type' => 'product-grid', 'props' => ['mode' => 'current', 'limit' => 12, 'width' => 'contained']],
    ],
]) !!}
