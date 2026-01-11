<?php

return [
    'reports' => [
        // Base weights per role. Higher roles start with more influence.
        'role_weights' => [
            'user' => 1.0,
            'maker' => 1.15,
            'moderator' => 1.6,
            'admin' => 2.0,
        ],

        // Final report weights are clamped to keep the system stable.
        'min_weight' => 1.0,
        'max_weight' => 12.0,

        // Activity scoring: active, long-term users earn more trusted reports.
        'activity' => [
            'post_points' => 8.0,
            'comment_points' => 2.0,
            'review_points' => 4.0,
            'follow_points' => 1.0,
            'upvote_points' => 0.25,
            'save_points' => 0.25,
            'age_points_per_day' => 0.35,
            'age_days_cap' => 365,
            'cap' => 180.0,
            'divisor' => 90.0,
        ],

        // Accuracy scoring: reporters with accepted reports get a boost, rejected reports reduce trust.
        'accuracy' => [
            'boost_max' => 0.6,
            'penalty_max' => 0.6,
            'min_multiplier' => 0.5,
            'min_trust' => 1.0,
            'max_trust' => 4.0,
        ],

        // Site scale adapts the auto-hide threshold to overall report volume.
        'site_scale' => [
            'window_days' => 7,
            'base_reports_per_day' => 12.0,
            'sensitivity' => 0.35,
            'min_scale' => 0.75,
            'max_scale' => 1.6,
            'cache_seconds' => 300,
        ],

        // Auto-hide: large report weight totals will hide content from the public feed.
        'auto_hide' => [
            'base_threshold' => 16.0,
            'question_multiplier' => 1.1,
            'minimum_reports' => 3,
        ],
    ],

    // Text moderation: lightweight heuristics to catch obviously low-quality spam.
    'text' => [
        'enabled' => env('TEXT_MODERATION_ENABLED', true),

        // Different content types can use different thresholds.
        'types' => [
            'post' => [
                'min_chars' => 180,
                'min_words' => 30,
                'score_threshold' => 2.6,
            ],
            'question' => [
                'min_chars' => 40,
                'min_words' => 8,
                'score_threshold' => 3.0,
            ],
        ],

        // Each signal contributes weight * severity to the final score.
        'signals' => [
            'too_short' => [
                'weight' => 1.5,
            ],
            'low_unique_words_ratio' => [
                'weight' => 1.2,
                'threshold' => 0.32,
            ],
            'repeated_words_ratio' => [
                'weight' => 1.1,
                'threshold' => 0.28,
                'min_words' => 20,
            ],
            'repeated_char_runs' => [
                'weight' => 1.3,
                'min_runs' => 1,
                'run_length' => 6,
            ],
            'uppercase_ratio' => [
                'weight' => 0.6,
                'threshold' => 0.55,
                'min_letters' => 30,
            ],
            'symbol_ratio' => [
                'weight' => 1.1,
                'threshold' => 0.9,
                'min_letters' => 20,
            ],
            'link_count' => [
                'weight' => 1.0,
                'threshold' => 4,
            ],
            'longest_line' => [
                'weight' => 0.6,
                'threshold' => 420,
            ],
        ],

        // Keep moderation details short and readable in logs.
        'details_limit' => 6,
    ],
];
