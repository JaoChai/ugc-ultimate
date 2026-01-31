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
        // Upload limits and timeouts
        'max_file_size' => env('R2_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB default
        'upload_timeout' => env('R2_UPLOAD_TIMEOUT', 120), // seconds
        'download_timeout' => env('R2_DOWNLOAD_TIMEOUT', 180), // seconds for large files
        // Retry configuration
        'retry_attempts' => env('R2_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('R2_RETRY_DELAY', 2), // base delay in seconds
    ],

    'kie' => [
        'webhook_secret' => env('KIE_WEBHOOK_SECRET'),
    ],

    'ffmpeg' => [
        'path' => env('FFMPEG_PATH', 'ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),
    ],

];
