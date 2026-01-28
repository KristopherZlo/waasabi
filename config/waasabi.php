<?php

return [
    'limits' => [
        'web' => [
            'guest_ip_per_minute' => 300,
            'user_per_minute' => 1200,
            'user_ip_per_minute' => 800,
        ],
        'auth' => [
            'login_ip_per_minute' => 10,
            'login_email_ip_per_minute' => 8,
            'register_ip_per_minute' => 5,
            'register_email_per_minute' => 3,
            'verification_ip_per_minute' => 6,
        ],
        'content' => [
            'publish_per_minute' => 6,
            'comment_per_minute' => 12,
            'review_per_minute' => 6,
            'report_per_minute' => 6,
            'post_action_per_minute' => 30,
            'post_action_ip_per_minute' => 60,
        ],
        'uploads' => [
            'images_per_minute' => 20,
            'images_per_minute_ip' => 40,
            'images_per_day_user' => 200,
        ],
        'support' => [
            'tickets_per_minute' => 6,
            'messages_per_minute' => 12,
        ],
        'profile' => [
            'media_per_minute' => 6,
            'follow_per_minute' => 20,
        ],
        'reading' => [
            'progress_per_minute' => 60,
            'read_later_per_minute' => 30,
        ],
    ],
    'security' => [
        'require_verified_for_content' => true,
        'min_account_age_minutes' => 10,
        'csp_report_only' => false,
        'csp_extra_script_src' => [],
        'csp_extra_style_src' => [],
        'csp_extra_connect_src' => [],
        'csp_extra_frame_src' => [],
    ],
    'upload' => [
        'max_image_mb' => 5,
        'max_images_per_post' => 8,
    ],
    'captcha' => [
        'enabled' => env('CAPTCHA_ENABLED', false),
        'provider' => env('CAPTCHA_PROVIDER', 'turnstile'),
        'site_key' => env('CAPTCHA_SITE_KEY'),
        'secret' => env('CAPTCHA_SECRET'),
        'actions' => [
            'register' => true,
            'login' => true,
            'verification' => true,
        ],
    ],
];
