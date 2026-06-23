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

    'unsplash' => [
        'url' => env('UNSPLASH_URL', 'https://api.unsplash.com'),
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
    ],

    'ai_provider' => [
        'url' => env('AI_PROVIDER_URL', 'https://free.wsd.my.id/v1'),
        'key' => env('AI_PROVIDER_API_KEY'),
        'model' => env('AI_PROVIDER_MODEL', 'free.wsd'),
    ],

];
