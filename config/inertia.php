<?php

return [
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
    ],
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/studio/pages')],
        'extensions' => ['js', 'jsx', 'ts', 'tsx'],
    ],
    'testing' => [
        'ensure_pages_exist' => true,
    ],
    'expose_shared_prop_keys' => true,
];
