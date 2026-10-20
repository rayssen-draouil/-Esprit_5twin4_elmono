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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai_classification' => [
        'url' => env('AI_CLASSIFICATION_URL', 'http://127.0.0.1:5000'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    ],

    'brevo' => [
        'driver' => env('BREVO_MAIL_DRIVER', env('MAIL_MAILER', 'smtp')),
        'api_key' => env('BREVO_API_KEY', env('brevo_api_key')),
        'smtp_host' => env('SMTP_HOST', env('MAIL_HOST', 'smtp-relay.brevo.com')),
        'smtp_port' => (int) env('SMTP_PORT', env('MAIL_PORT', 587)),
        'smtp_user' => env('SMTP_USER', env('MAIL_USERNAME')),
        'smtp_pass' => env('SMTP_PASS', env('MAIL_PASSWORD')),
        'sender_name' => env('BREVO_SENDER_NAME', env('brevo_sender_name', env('MAIL_FROM_NAME', 'AquaSecure'))),
        'sender_email' => env('BREVO_SENDER_EMAIL', env('brevo_sender_email', env('EMAIL_FROM', env('MAIL_FROM_ADDRESS', 'miladiadem58@gmail.com')))),
        'timeout' => (int) env('BREVO_TIMEOUT', 15),
        'ssl_verify' => env('BREVO_SSL_VERIFY', false),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'base_url' => rtrim(env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 15),
    ],

];
