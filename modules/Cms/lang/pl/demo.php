<?php

declare(strict_types=1);

return [
    'pages' => [
        'start' => [
            'slug' => 'start',
            'title' => 'Dobry design na co dzień',
            'seo' => 'Wybrane przedmioty do domu i pracy',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Przedmioty, które dobrze się starzeją',
                        'subtitle' => 'Proste formy, naturalne materiały i rzeczy stworzone do codziennego używania.',
                        'button_label' => 'Zobacz produkty',
                        'button_url' => '/{locale}/products',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Polecane produkty',
                        'level' => '2',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'product-grid',
                    'props' => [
                        'mode' => 'featured',
                        'category_id' => '',
                        'product_ids' => [],
                        'limit' => 6,
                        'width' => 'contained',
                    ],
                ],
            ],
        ],
        'about' => [
            'slug' => 'o-nas',
            'title' => 'O nas',
            'seo' => 'Poznaj przykładowy sklep i jego wartości',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Mniej, ale lepiej',
                        'subtitle' => 'Wybieramy produkty funkcjonalne, trwałe i zaprojektowane z szacunkiem do materiału.',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Nasze podejście</h2><p>Wierzymy, że codzienne przedmioty powinny być użyteczne, czytelne i przyjemne w dotyku. Współpracujemy z małymi pracowniami i markami, które potrafią opowiedzieć, skąd pochodzą ich materiały.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'shipping_payment' => [
            'slug' => 'dostawa-i-platnosc',
            'title' => 'Dostawa i płatność',
            'seo' => 'Informacje o dostawie i płatnościach',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Dostawa i płatność',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Dostawa</h2><p>Zamówienia przygotowujemy zwykle w ciągu jednego dnia roboczego. Dostępne metody i ich aktualne ceny zobaczysz w koszyku.</p><h2>Płatność</h2><p>Możesz skorzystać z metod płatności skonfigurowanych w panelu sklepu.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'contact' => [
            'slug' => 'kontakt',
            'title' => 'Kontakt',
            'seo' => 'Skontaktuj się z przykładowym sklepem',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Porozmawiajmy',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<p>Masz pytanie o produkt, zamówienie lub pielęgnację materiału? Napisz do nas — odpowiadamy w dni robocze.</p><h2>Dane kontaktowe</h2><p>E-mail: kontakt@example.com<br>Telefon: +48 000 000 000</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'faq' => [
            'slug' => 'najczestsze-pytania',
            'title' => 'Najczęstsze pytania',
            'seo' => 'Odpowiedzi na pytania o produkty i zamówienia',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Najczęstsze pytania',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Czy produkty mogą różnić się wyglądem?</h2><p>Tak. Naturalne materiały i ręczne wykończenie powodują subtelne różnice koloru, faktury i wymiaru.</p><h2>Jak szybko wysyłacie zamówienia?</h2><p>Gotowe produkty przygotowujemy zwykle w ciągu jednego dnia roboczego.</p><h2>Czy mogę zmienić zamówienie?</h2><p>Skontaktuj się z nami możliwie szybko — pomożemy, jeśli paczka nie została jeszcze przekazana do wysyłki.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'care' => [
            'slug' => 'pielegnacja',
            'title' => 'Pielęgnacja produktów',
            'seo' => 'Jak dbać o ceramikę, len i drewno',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Pielęgnacja produktów',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Ceramika</h2><p>Unikaj gwałtownych zmian temperatury i zawsze dokładnie susz naczynia.</p><h2>Len</h2><p>Pierz w 30–40°C, bez płynu zmiękczającego, i susz naturalnie.</p><h2>Drewno</h2><p>Nie namaczaj. Co pewien czas odśwież powierzchnię cienką warstwą oleju do kontaktu z żywnością.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'materials' => [
            'slug' => 'materialy',
            'title' => 'Nasze materiały',
            'seo' => 'Poznaj materiały używane w przykładowych produktach',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Materiały, które dobrze się starzeją',
                        'subtitle' => 'Wybieramy ceramikę, len, lite drewno, papier i metal ze względu na ich trwałość oraz charakter.',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Naturalna zmienność</h2><p>Usłojenie, drobne różnice szkliwa i zagniecenia lnu są częścią materiału. Nie maskujemy ich, bo dzięki nim każdy przedmiot nabiera własnego charakteru.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'returns' => [
            'slug' => 'zwroty-i-reklamacje',
            'title' => 'Zwroty i reklamacje',
            'seo' => 'Informacje o zwrotach i reklamacjach',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Zwroty i reklamacje',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Zwrot</h2><p>Jeśli produkt nie spełnia oczekiwań, skorzystaj z zasad zwrotu obowiązujących w sklepie.</p><h2>Reklamacja</h2><p>Opisz problem i dołącz zdjęcia. Odpowiemy z dalszymi krokami oraz bezpiecznym sposobem odesłania produktu.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'gift_guide' => [
            'slug' => 'przewodnik-prezentowy',
            'title' => 'Przewodnik prezentowy',
            'seo' => 'Pomysły na praktyczne prezenty do domu i pracy',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Prezent dopasowany do codzienności',
                        'subtitle' => 'Wybierz przedmiot związany z poranną kawą, spokojną pracą albo wieczornym odpoczynkiem.',
                        'button_label' => 'Zobacz produkty',
                        'button_url' => '/{locale}/products',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Polecane na prezent',
                        'level' => '2',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'product-grid',
                    'props' => [
                        'mode' => 'featured',
                        'category_id' => '',
                        'product_ids' => [],
                        'limit' => 9,
                        'width' => 'contained',
                    ],
                ],
            ],
        ],
    ],
    'menu' => [
        'name' => 'Menu demonstracyjne',
        'home' => 'Strona główna',
        'products' => 'Produkty',
        'about' => 'O nas',
        'shipping_payment' => 'Dostawa i płatność',
        'contact' => 'Kontakt',
        'blog' => 'Blog',
    ],
];
