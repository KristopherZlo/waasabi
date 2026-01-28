<?php

return [
    // Role order defines inheritance: higher roles inherit lower roles.
    'order' => ['user', 'maker', 'support', 'moderator', 'admin'],

    // Optional ability map per role. Abilities are inherited from lower roles.
    'abilities' => [
        'user' => [
            'view_content',
            'publish',
            'comment',
            'follow',
            'report',
            'save',
            'vote',
        ],
        'maker' => [
            'publish',
        ],
        'support' => [
            'support',
        ],
        'moderator' => [
            'moderate',
            'ban',
        ],
        'admin' => [
            'admin',
            'grant_badge',
            'revoke_badge',
        ],
    ],
    'maker_promotion' => [
        'required_posts' => 5,
        'min_upvotes' => 15,
        'percentile' => 75,
        'window_hours' => 24,
        'min_sample' => 10,
        'exclude_nsfw' => true,
        'require_visible' => true,
        'require_approved' => true,
        'type' => 'post',
        'cache_minutes' => 10,
    ],
];
