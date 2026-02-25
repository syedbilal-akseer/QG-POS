<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for WhatsApp Business API integration.
    | You need to set up a WhatsApp Business Account and get the required credentials
    | from Facebook Developer Console.
    |
    */

    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v22.0'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Message Settings
    |--------------------------------------------------------------------------
    */
    'max_retries' => env('WHATSAPP_MAX_RETRIES', 3),
    
    'retry_delay' => env('WHATSAPP_RETRY_DELAY', 5), // seconds

    'timeout' => env('WHATSAPP_TIMEOUT', 30), // seconds

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    */
    'max_file_size' => env('WHATSAPP_MAX_FILE_SIZE', 100), // MB
    
    'allowed_file_types' => [
        'document' => ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'mov', 'avi', '3gp'],
        'audio' => ['mp3', 'wav', 'ogg', 'aac'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'messages_per_second' => env('WHATSAPP_MESSAGES_PER_SECOND', 10),
        'burst_limit' => env('WHATSAPP_BURST_LIMIT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Messages
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'invoice_notification' => [
            'name' => 'invoice_notification',
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'Invoice from QG Distributors']
                    ]
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => '{{customer_name}}'],
                        ['type' => 'text', 'text' => '{{customer_code}}'],
                        ['type' => 'text', 'text' => '{{invoice_number}}'],
                        ['type' => 'text', 'text' => '{{amount}}'],
                    ]
                ]
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('WHATSAPP_LOGGING_ENABLED', true),
        'log_channel' => env('WHATSAPP_LOG_CHANNEL', 'default'),
        'log_level' => env('WHATSAPP_LOG_LEVEL', 'info'),
    ],
];