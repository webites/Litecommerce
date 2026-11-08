<?php

// Shared by every block type below — "full width" breaks a block out of
// theme::layout's centered .sf-main via Cms\Infrastructure\ThemeBlockRenderer's
// wrapping <div class="cms-block cms-block--{width}">, not a per-block Blade change.
$widthField = [
    'type' => 'select',
    'label' => 'Szerokość',
    'options' => [
        ['label' => 'W kontenerze', 'value' => 'contained'],
        ['label' => 'Pełna szerokość', 'value' => 'full'],
    ],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Page builder blocks
    |--------------------------------------------------------------------------
    |
    | The single source of truth for the page builder's block types — the
    | panel's React editor (packages/page-builder) turns this into a Puck
    | config via buildPuckConfig(), and Cms\Infrastructure\BlockRenderer
    | reads the same "type" keys to resolve theme::blocks.{type} on the
    | storefront. Core and built-in optional modules may append definitions
    | during provider boot (Blog does this); third-party block-definition
    | registration remains outside v1. Dynamic view data is resolved through
    | Cms\Contracts\BlockDataRegistry so an optional module never becomes a
    | hard constructor dependency of CMS.
    |
    | Each field's "type" is either a native Puck field type (text,
    | textarea, number, select, radio, array, object), Puck's own "slot"
    | (a nested drop zone for other blocks — see 'container' below), or one
    | of this project's own custom field types (product-picker, image-picker),
    | mapped to a real Puck field by buildPuckConfig() in
    | packages/page-builder/src/buildConfig.js.
    |
    */

    'blocks' => [

        'hero' => [
            'label' => 'Hero / baner',
            'category' => 'layout',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Nagłówek'],
                'subtitle' => ['type' => 'textarea', 'label' => 'Podtytuł'],
                'cta_label' => ['type' => 'text', 'label' => 'Etykieta przycisku'],
                'cta_url' => ['type' => 'text', 'label' => 'Link przycisku'],
                'background' => ['type' => 'image-picker', 'label' => 'Tło'],
                'text_theme' => [
                    'type' => 'select',
                    'label' => 'Kolor tekstu',
                    'options' => [
                        ['label' => 'Ciemny (na jasnym tle)', 'value' => 'light'],
                        ['label' => 'Jasny (na ciemnym tle)', 'value' => 'dark'],
                    ],
                ],
                'width' => $widthField,
            ],
            'default_props' => [
                'title' => 'Nagłówek',
                'subtitle' => '',
                'cta_label' => '',
                'cta_url' => '',
                'background' => null,
                'text_theme' => 'light',
                'width' => 'contained',
            ],
        ],

        'rich-text' => [
            'label' => 'Tekst',
            'category' => 'content',
            'fields' => [
                // Puck's own built-in TipTap-based WYSIWYG (bold/italic/lists/links/
                // code/quote toolbar) — no HTML knowledge needed to edit this block.
                // Its value is a plain HTML string (Editor::getHTML()), so nothing
                // else downstream (ThemeBlockRenderer, the JS preview) has to change.
                'html' => ['type' => 'richtext', 'label' => 'Treść', 'initialHeight' => 200],
                'width' => $widthField,
            ],
            'default_props' => [
                'html' => '',
                'width' => 'contained',
            ],
        ],

        'image-banner' => [
            'label' => 'Baner obrazkowy',
            'category' => 'content',
            'fields' => [
                'image' => ['type' => 'image-picker', 'label' => 'Obraz'],
                'alt' => ['type' => 'text', 'label' => 'Tekst alternatywny'],
                'caption' => ['type' => 'text', 'label' => 'Podpis (opcjonalny)'],
                'link_url' => ['type' => 'text', 'label' => 'Link (opcjonalny)'],
                'width' => $widthField,
            ],
            'default_props' => [
                'image' => null,
                'alt' => '',
                'caption' => '',
                'link_url' => '',
                'width' => 'contained',
            ],
        ],

        'heading' => [
            'label' => 'Nagłówek',
            'category' => 'content',
            'fields' => [
                'text' => ['type' => 'text', 'label' => 'Treść'],
                // H1 allowed (2026-09-19 revision — was reserved for 'hero' only):
                // an archetype template's own heading (e.g. the category name) is a
                // legitimate, common use for a page-level H1 outside a hero banner.
                'level' => [
                    'type' => 'select',
                    'label' => 'Poziom',
                    'options' => [
                        ['label' => 'H1', 'value' => '1'],
                        ['label' => 'H2', 'value' => '2'],
                        ['label' => 'H3', 'value' => '3'],
                        ['label' => 'H4', 'value' => '4'],
                    ],
                ],
                'width' => $widthField,
            ],
            'default_props' => [
                'text' => 'Nagłówek',
                'level' => '2',
                'width' => 'contained',
            ],
        ],

        'list' => [
            'label' => 'Lista',
            'category' => 'content',
            'fields' => [
                'style' => [
                    'type' => 'select',
                    'label' => 'Rodzaj',
                    'options' => [
                        ['label' => 'Wypunktowana', 'value' => 'unordered'],
                        ['label' => 'Numerowana', 'value' => 'ordered'],
                    ],
                ],
                'items' => [
                    'type' => 'array',
                    'label' => 'Pozycje',
                    'fields' => [
                        'text' => ['type' => 'text', 'label' => 'Treść'],
                    ],
                    'default_item_props' => ['text' => ''],
                ],
                'width' => $widthField,
            ],
            'default_props' => [
                'style' => 'unordered',
                'items' => [['text' => 'Pozycja listy']],
                'width' => 'contained',
            ],
        ],

        'button' => [
            'label' => 'Przycisk',
            'category' => 'content',
            'fields' => [
                'label' => ['type' => 'text', 'label' => 'Etykieta'],
                'url' => ['type' => 'text', 'label' => 'Link'],
                'style' => [
                    'type' => 'select',
                    'label' => 'Styl',
                    'options' => [
                        ['label' => 'Główny', 'value' => 'primary'],
                        ['label' => 'Drugorzędny', 'value' => 'secondary'],
                    ],
                ],
                'open_in_new_tab' => [
                    'type' => 'radio',
                    'label' => 'Otwieranie linku',
                    'options' => [
                        ['label' => 'Ta sama karta', 'value' => ''],
                        ['label' => 'Nowa karta', 'value' => '_blank'],
                    ],
                ],
                'width' => $widthField,
            ],
            'default_props' => [
                'label' => 'Kliknij tutaj',
                'url' => '',
                'style' => 'primary',
                'open_in_new_tab' => '',
                'width' => 'contained',
            ],
        ],

        // Deliberately YouTube-only, not a generic multi-provider oEmbed system —
        // see docs/knowledge-base/09-presentation.md's Gutenberg research note.
        'video' => [
            'label' => 'Wideo (YouTube)',
            'category' => 'content',
            'fields' => [
                'url' => ['type' => 'text', 'label' => 'Link do filmu YouTube'],
                'caption' => ['type' => 'text', 'label' => 'Podpis (opcjonalny)'],
                'width' => $widthField,
            ],
            'default_props' => [
                'url' => '',
                'caption' => '',
                'width' => 'contained',
            ],
        ],

        'quote' => [
            'label' => 'Cytat',
            'category' => 'content',
            'fields' => [
                'text' => ['type' => 'textarea', 'label' => 'Treść cytatu'],
                'citation' => ['type' => 'text', 'label' => 'Autor / źródło (opcjonalne)'],
                'width' => $widthField,
            ],
            'default_props' => [
                'text' => '',
                'citation' => '',
                'width' => 'contained',
            ],
        ],

        'separator' => [
            'label' => 'Separator',
            'category' => 'layout',
            'fields' => [
                'width' => $widthField,
            ],
            'default_props' => [
                'width' => 'contained',
            ],
        ],

        'spacer' => [
            'label' => 'Odstęp',
            'category' => 'layout',
            'fields' => [
                'height' => [
                    'type' => 'select',
                    'label' => 'Wysokość',
                    'options' => [
                        ['label' => 'Mały', 'value' => 'sm'],
                        ['label' => 'Średni', 'value' => 'md'],
                        ['label' => 'Duży', 'value' => 'lg'],
                        ['label' => 'Bardzo duży', 'value' => 'xl'],
                    ],
                ],
                'width' => $widthField,
            ],
            'default_props' => [
                'height' => 'md',
                'width' => 'contained',
            ],
        ],

        'product-grid' => [
            'label' => 'Siatka produktów',
            'category' => 'catalog',
            'fields' => [
                'mode' => [
                    'type' => 'select',
                    'label' => 'Źródło produktów',
                    'options' => [
                        // "Bieżący widok" only resolves to anything on an archetype
                        // page (category/brand archive) — Cms\Infrastructure\
                        // ThemeBlockRenderer reads it from the render-time $context,
                        // not from this block's own props. Elsewhere it renders empty.
                        ['label' => 'Bieżący widok (kategoria/marka)', 'value' => 'current'],
                        ['label' => 'Kategoria', 'value' => 'category'],
                        ['label' => 'Wyróżnione', 'value' => 'featured'],
                        ['label' => 'Wybrane ręcznie', 'value' => 'manual'],
                    ],
                ],
                // 'options' is filled at request time by Cms\Presentation\Controllers\PageController
                // with the live category list — Catalog\Contracts\CategoryCatalog::listAll().
                'category_id' => ['type' => 'select', 'label' => 'Kategoria', 'options' => []],
                'product_ids' => ['type' => 'product-picker', 'label' => 'Produkty', 'multiple' => true],
                'limit' => ['type' => 'number', 'label' => 'Liczba produktów'],
                'width' => $widthField,
            ],
            'default_props' => [
                'mode' => 'category',
                'category_id' => '',
                'product_ids' => [],
                'limit' => 8,
                'width' => 'contained',
            ],
        ],

        // Layout primitive — a row (flex) or grid of up to 4 columns, each its
        // own Puck "slot" (a nested drop zone any other block, including
        // another container, can be placed into — grid-in-grid works because
        // no 'allow'/'disallow' restricts it). Cms\Infrastructure\ThemeBlockRenderer
        // renders each slot's block list recursively via the same renderBlocks().
        'container' => [
            'label' => 'Kontener (kolumny)',
            'category' => 'layout',
            'fields' => [
                'layout' => [
                    'type' => 'select',
                    'label' => 'Układ',
                    'options' => [
                        ['label' => 'Rząd (flex)', 'value' => 'flex'],
                        ['label' => 'Siatka (grid)', 'value' => 'grid'],
                    ],
                ],
                'columns' => [
                    'type' => 'select',
                    'label' => 'Liczba kolumn',
                    'options' => [
                        ['label' => '1', 'value' => '1'],
                        ['label' => '2', 'value' => '2'],
                        ['label' => '3', 'value' => '3'],
                        ['label' => '4', 'value' => '4'],
                    ],
                ],
                'gap' => [
                    'type' => 'select',
                    'label' => 'Odstęp',
                    'options' => [
                        ['label' => 'Mały', 'value' => 'sm'],
                        ['label' => 'Średni', 'value' => 'md'],
                        ['label' => 'Duży', 'value' => 'lg'],
                    ],
                ],
                'ratio' => [
                    'type' => 'select',
                    'label' => 'Proporcje kolumn',
                    'options' => [
                        ['label' => 'Równe', 'value' => 'equal'],
                        ['label' => 'Szeroka lewa (2:1)', 'value' => '2-1'],
                        ['label' => 'Szeroka lewa (3:1)', 'value' => '3-1'],
                        ['label' => 'Szeroka prawa (1:2)', 'value' => '1-2'],
                    ],
                ],
                'column_1' => ['type' => 'slot', 'label' => 'Kolumna 1'],
                'column_2' => ['type' => 'slot', 'label' => 'Kolumna 2'],
                'column_3' => ['type' => 'slot', 'label' => 'Kolumna 3'],
                'column_4' => ['type' => 'slot', 'label' => 'Kolumna 4'],
                'width' => $widthField,
            ],
            'default_props' => [
                'layout' => 'flex',
                'columns' => '2',
                'gap' => 'md',
                'ratio' => 'equal',
                'column_1' => [],
                'column_2' => [],
                'column_3' => [],
                'column_4' => [],
                'width' => 'contained',
            ],
        ],

        // The Gutenberg "Query Title"/"Term Description" equivalent — shows the
        // name/description of whatever archetype page is currently being viewed
        // (category, brand, ...). Has no content fields of its own: the same
        // stored template is reused for every matching category/brand, so its
        // text can only ever come from Cms\Infrastructure\ThemeBlockRenderer's
        // render-time $context, never typed in here.
        'archive-heading' => [
            'label' => 'Nagłówek archiwum',
            'category' => 'catalog',
            'fields' => [
                'show_description' => [
                    'type' => 'radio',
                    'label' => 'Pokaż opis',
                    'options' => [
                        ['label' => 'Tak', 'value' => '1'],
                        ['label' => 'Nie', 'value' => ''],
                    ],
                ],
                'width' => $widthField,
            ],
            'default_props' => [
                'show_description' => '1',
                'width' => 'contained',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Archetype template root fields
    |--------------------------------------------------------------------------
    |
    | Used instead of 'root_fields' below when editing a whole-archetype
    | override (Cms\Presentation\Controllers\ArchetypeTemplateController) —
    | no slug/SEO, since the same template applies to many different
    | categories/brands, each already resolved at render time by
    | Cms\Infrastructure\ThemeArchetypeResolver.
    |
    */

    // No 'status' field here on purpose — unlike a Cms page, saving an
    // archetype template's builder always publishes it immediately (see
    // Cms\Application\UpdateArchetypeTemplate); "draft" only ever exists
    // transiently on a freshly, lazily created row nobody has saved yet.
    // Going back to the theme's own default is the dedicated "reset"
    // action (deletes the row), not a status toggle.
    'archetype_root_fields' => [
        'title' => ['type' => 'text', 'label' => 'Nazwa szablonu (wewnętrzna)'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Page builder root fields
    |--------------------------------------------------------------------------
    |
    | Puck's "root" is the page itself, not a block — putting title/slug/SEO/
    | status here means the builder is the single editing surface for a page
    | (opened full-screen, see Panel/Cms/Edit.jsx), instead of a separate
    | metadata form squeezed above an embedded editor. Same field-type idiom
    | as 'blocks' above, mapped by the same buildPuckConfig().
    |
    */

    'root_fields' => [
        'title' => ['type' => 'text', 'label' => 'Tytuł strony'],
        'slug' => ['type' => 'text', 'label' => 'Adres (slug)'],
        'seo_title' => ['type' => 'text', 'label' => 'Tytuł SEO'],
        'seo_description' => ['type' => 'textarea', 'label' => 'Opis SEO'],
        'status' => [
            'type' => 'select',
            'label' => 'Status',
            'options' => [
                ['label' => 'Szkic', 'value' => 'draft'],
                ['label' => 'Opublikowana', 'value' => 'published'],
                ['label' => 'Zarchiwizowana', 'value' => 'archived'],
            ],
        ],
    ],

];
