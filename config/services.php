<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have a
    | conventional file to locate the various service credentials.
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

    'labsmobile' => [
        'enabled' => env('LABSMOBILE_ENABLED', false),
        'username' => env('LABSMOBILE_USERNAME'),
        'token' => env('LABSMOBILE_TOKEN'),
        'endpoint' => env('LABSMOBILE_ENDPOINT', 'https://api.labsmobile.com/json/send'),
        'balance_endpoint' => env('LABSMOBILE_BALANCE_ENDPOINT', 'https://api.labsmobile.com/json/balance'),
        'prices_endpoint' => env('LABSMOBILE_PRICES_ENDPOINT', 'https://api.labsmobile.com/json/prices'),
        'test_mode' => env('LABSMOBILE_TEST_MODE', true),
        'ack_url' => env('LABSMOBILE_ACK_URL'),
        'webhook_token' => env('LABSMOBILE_WEBHOOK_TOKEN'),
    ],

    'whatsapp_cloud' => [
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-terra'),
    ],

    'document_security' => [
        'clamav_binary' => env('CLAMAV_BINARY'),
    ],

];
