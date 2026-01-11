<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rekognition' => [
        'enabled' => env('AWS_REKOGNITION_ENABLED', false),
        'region' => env('AWS_REKOGNITION_REGION', env('AWS_DEFAULT_REGION', 'eu-north-1')),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'min_confidence' => env('AWS_REKOGNITION_MIN_CONFIDENCE', 75),
        'sexual_labels' => env('AWS_REKOGNITION_SEXUAL_LABELS', 'Explicit Nudity,Sexual Activity,Partial Nudity,Suggestive'),
        'fallback_manual' => env('AWS_REKOGNITION_FALLBACK_MANUAL', true),
        'fallback_action' => env(
            'AWS_REKOGNITION_FALLBACK_ACTION',
            env('AWS_REKOGNITION_FALLBACK_MANUAL', true) ? 'mod' : 'post',
        ),
    ],

];
