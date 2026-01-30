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

    'r2' => [
        'account_id' => env('R2_ACCOUNT_ID'),
        'access_key_id' => env('R2_ACCESS_KEY_ID'),
        'secret_access_key' => env('R2_SECRET_ACCESS_KEY'),
        'bucket' => env('R2_BUCKET', 'ugc-ultimate'),
        'public_url' => env('R2_PUBLIC_URL'),
    ],

    'kie' => [
        'webhook_secret' => env('KIE_WEBHOOK_SECRET'),
    ],

    'ffmpeg' => [
        'path' => env('FFMPEG_PATH', 'ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),
    ],

];
