<?php

return [
    // Role order defines inheritance: higher roles inherit lower roles.
    'order' => ['user', 'maker', 'support', 'moderator', 'admin'],

    // Optional ability map per role. Abilities are inherited from lower roles.
    'abilities' => [
        'user' => [
            'view_content',
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
];
