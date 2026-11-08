{{--
    Theme's default block-based template for the 'category' archetype —
    resolved via the same theme:: namespace fallback as views/blocks/*
    (Cms\Infrastructure\ThemeArchetypeResolver). A merchant's own published
    override (Panel > Treści > Szablony archiwów) replaces this entirely;
    this file is plain JSON output, not real markup — do not add other
    Blade directives here.
--}}
{!! json_encode([
    'content' => [
        ['type' => 'archive-heading', 'props' => ['show_description' => '1', 'width' => 'contained']],
        ['type' => 'product-grid', 'props' => ['mode' => 'current', 'limit' => 12, 'width' => 'contained']],
    ],
]) !!}
