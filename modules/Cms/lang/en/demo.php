<?php

declare(strict_types=1);

return [
    'pages' => [
        'start' => [
            'slug' => 'home',
            'title' => 'Good design for every day',
            'seo' => 'Selected objects for home and work',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Objects that age well',
                        'subtitle' => 'Simple forms, natural materials and things made for everyday use.',
                        'button_label' => 'See products',
                        'button_url' => '/{locale}/products',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Featured products',
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
            'slug' => 'about-us',
            'title' => 'About us',
            'seo' => 'Get to know the sample store and its values',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Less, but better',
                        'subtitle' => 'We choose products that are functional, durable and designed with respect for the material.',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Our approach</h2><p>We believe everyday objects should be useful, clear and pleasant to the touch. We work with small studios and brands that can tell you where their materials come from.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'shipping_payment' => [
            'slug' => 'shipping-and-payment',
            'title' => 'Shipping and payment',
            'seo' => 'Information about delivery and payments',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Shipping and payment',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Shipping</h2><p>We usually prepare orders within one business day. The available methods and their current prices are shown in the cart.</p><h2>Payment</h2><p>You can use the payment methods configured in the store panel.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'contact' => [
            'slug' => 'contact',
            'title' => 'Contact',
            'seo' => 'Get in touch with the sample store',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Let\'s talk',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<p>Do you have a question about a product, an order or caring for a material? Write to us — we reply on business days.</p><h2>Contact details</h2><p>Email: contact@example.com<br>Phone: +1 555 000 0000</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'faq' => [
            'slug' => 'faq',
            'title' => 'Frequently asked questions',
            'seo' => 'Answers to questions about products and orders',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Frequently asked questions',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Can products look different from the photos?</h2><p>Yes. Natural materials and handmade finishing cause subtle differences in color, texture and size.</p><h2>How quickly do you ship orders?</h2><p>We usually prepare ready products within one business day.</p><h2>Can I change my order?</h2><p>Contact us as soon as possible — we will help if the parcel has not been handed over for shipping yet.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'care' => [
            'slug' => 'product-care',
            'title' => 'Product care',
            'seo' => 'How to look after ceramics, linen and wood',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Product care',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Ceramics</h2><p>Avoid sudden changes in temperature and always dry vessels thoroughly.</p><h2>Linen</h2><p>Wash at 30–40°C without fabric softener and dry naturally.</p><h2>Wood</h2><p>Do not soak it. From time to time refresh the surface with a thin layer of food-safe oil.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'materials' => [
            'slug' => 'materials',
            'title' => 'Our materials',
            'seo' => 'Get to know the materials used in the sample products',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'Materials that age well',
                        'subtitle' => 'We choose ceramics, linen, solid wood, paper and metal for their durability and character.',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Natural variation</h2><p>Wood grain, small differences in glaze and creases in linen are part of the material. We do not hide them, because they give every object its own character.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'returns' => [
            'slug' => 'returns-and-complaints',
            'title' => 'Returns and complaints',
            'seo' => 'Information about returns and complaints',
            'content' => [
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Returns and complaints',
                        'level' => '1',
                        'width' => 'narrow',
                    ],
                ],
                [
                    'type' => 'rich-text',
                    'props' => [
                        'html' => '<h2>Returns</h2><p>If a product does not meet your expectations, use the return rules that apply in the store.</p><h2>Complaints</h2><p>Describe the problem and attach photos. We will reply with the next steps and a safe way to send the product back.</p>',
                        'width' => 'narrow',
                    ],
                ],
            ],
        ],
        'gift_guide' => [
            'slug' => 'gift-guide',
            'title' => 'Gift guide',
            'seo' => 'Ideas for practical gifts for home and work',
            'content' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'title' => 'A gift that fits everyday life',
                        'subtitle' => 'Choose an object tied to a morning coffee, calm work or an evening of rest.',
                        'button_label' => 'See products',
                        'button_url' => '/{locale}/products',
                        'align' => 'left',
                        'style' => 'light',
                        'width' => 'contained',
                    ],
                ],
                [
                    'type' => 'heading',
                    'props' => [
                        'text' => 'Recommended as gifts',
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
        'name' => 'Demo menu',
        'home' => 'Home',
        'products' => 'Products',
        'about' => 'About us',
        'shipping_payment' => 'Shipping and payment',
        'contact' => 'Contact',
        'blog' => 'Blog',
    ],
];
