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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'shopify' => [
        'key' => env('SHOPIFY_API_KEY'),
        'secret' => env('SHOPIFY_API_SECRET'),
        'scopes' => env('SHOPIFY_APP_SCOPES'),
        'app_url' => env('SHOPIFY_APP_URL'),
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'billing_enabled' => env('SHOPIFY_BILLING_ENABLED', false),
    ],

    'ebay' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'redirect_uri' => env('EBAY_REDIRECT_URI'),
        'env' => env('EBAY_ENV', 'sandbox'),
    ],

    'amazon' => [
        'lwa_client_id' => env('AMZ_LWA_CLIENT_ID'),
        'lwa_client_secret' => env('AMZ_LWA_CLIENT_SECRET'),
        'aws_key' => env('AMZ_AWS_ACCESS_KEY_ID'),
        'aws_secret' => env('AMZ_AWS_SECRET_ACCESS_KEY'),
        'region' => env('AMZ_REGION', 'us-east-1'),
        'host_na' => env('AMZ_API_HOST_NA', 'sellingpartnerapi-na.amazon.com'),
        'host_eu' => env('AMZ_API_HOST_EU', 'sellingpartnerapi-eu.amazon.com'),
        'host_fe' => env('AMZ_API_HOST_FE', 'sellingpartnerapi-fe.amazon.com'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
        'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
    ],

    'walmart' => [
        'client_id' => env('WALMART_CLIENT_ID'),
        'client_secret' => env('WALMART_CLIENT_SECRET'),
        'sandbox' => env('WALMART_SANDBOX', true),
    ],

    'etsy' => [
        'client_id' => env('ETSY_CLIENT_ID'),
        'client_secret' => env('ETSY_CLIENT_SECRET'),
        'redirect_uri' => env('ETSY_REDIRECT_URI'),
        'scopes' => env('ETSY_SCOPES', 'listings_r listings_w transactions_r shops_r'),
    ],

    'quickbooks' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
        'scopes' => env('QUICKBOOKS_SCOPES', 'com.intuit.quickbooks.accounting'),
        'sandbox' => env('QUICKBOOKS_SANDBOX', true),
    ],

];
