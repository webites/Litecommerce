{!! json_encode([
    'content' => [
        [
            'type' => 'container',
            'props' => [
                'layout' => 'grid',
                'columns' => '2',
                'ratio' => '2-1',
                'gap' => 'lg',
                'width' => 'contained',
                'column_1' => [
                    ['type' => 'blog-post-content', 'props' => ['show_excerpt' => '1', 'show_featured_image' => '1', 'width' => 'contained']],
                ],
                'column_2' => [
                    ['type' => 'heading', 'props' => ['text' => 'Podobne posty', 'level' => '2', 'width' => 'contained']],
                    ['type' => 'blog-post-list', 'props' => ['mode' => 'current', 'layout' => 'list', 'show_excerpt' => '0', 'limit' => 4, 'width' => 'contained']],
                    ['type' => 'separator', 'props' => ['width' => 'contained']],
                    ['type' => 'heading', 'props' => ['text' => 'Kategorie bloga', 'level' => '2', 'width' => 'contained']],
                    ['type' => 'blog-category-list', 'props' => ['layout' => 'list', 'width' => 'contained']],
                ],
            ],
        ],
    ],
]) !!}
