{{--
    Theme's default block-based homepage content — "starting data" so a
    fresh shop (or one that just switched themes) has an editable, non-blank
    "/" instead of the bare product-listing fallback. Resolved via the same
    theme:: namespace fallback as archetypes/blocks (Cms\Infrastructure\
    ThemeDefaultContentResolver). Imported into the actual is_homepage=true
    CmsPage row by Cms\Application\ResetHomepageToThemeDefault, then freely
    editable like any other page builder content — this file is plain JSON
    output, not real markup, do not add other Blade directives here.
--}}
{!! json_encode([
    'content' => [
        ['type' => 'hero', 'props' => [
            'title' => 'Witaj w sklepie',
            'subtitle' => 'Odkryj nasze produkty.',
            'cta_label' => 'Zobacz produkty',
            'cta_url' => '/products',
            'text_theme' => 'light',
            'width' => 'contained',
        ]],
        ['type' => 'product-grid', 'props' => ['mode' => 'featured', 'limit' => 8, 'width' => 'contained']],
    ],
]) !!}
