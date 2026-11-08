{!! json_encode([
    'content' => [
        ['type' => 'archive-heading', 'props' => ['show_description' => '1', 'width' => 'contained']],
        ['type' => 'blog-post-list', 'props' => ['mode' => 'current', 'layout' => 'grid', 'show_excerpt' => '1', 'limit' => 12, 'width' => 'contained']],
    ],
]) !!}
