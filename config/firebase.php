<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (FCM) configuration
    |--------------------------------------------------------------------------
    |
    | The service-account JSON should be stored OUTSIDE the public web root
    | and OUTSIDE git. Recommended path: storage/app/firebase/service-account.json
    |
    | After rotating the service-account key in Firebase Console, save the new
    | JSON file at FIREBASE_CREDENTIALS_PATH and set FIREBASE_PROJECT_ID in .env.
    */

    'credentials_path' => env(
        'FIREBASE_CREDENTIALS_PATH',
        storage_path('app/firebase/service-account.json')
    ),

    'project_id' => env('FIREBASE_PROJECT_ID', 'qgpos-a8b51'),

    // Cache key + TTL (in seconds) for the OAuth access token returned by Google.
    // Google's tokens expire in 3600s; we cache for 3000s as a safety buffer.
    'oauth_cache_key' => 'fcm.oauth_access_token',
    'oauth_cache_ttl' => 3000,

    // FCM v1 endpoint — uses the project_id above.
    'fcm_endpoint'    => 'https://fcm.googleapis.com/v1/projects/%s/messages:send',
    'oauth_token_url' => 'https://oauth2.googleapis.com/token',
];
