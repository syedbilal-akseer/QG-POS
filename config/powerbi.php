<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Power BI Embedded Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Power BI Embedded integration.
    | You need to create an Azure AD app and grant it permissions to Power BI.
    |
    */

    'client_id' => env('POWERBI_CLIENT_ID'),
    'client_secret' => env('POWERBI_CLIENT_SECRET'),
    'tenant_id' => env('POWERBI_TENANT_ID'),
    'workspace_id' => env('POWERBI_WORKSPACE_ID'),
    'report_id' => env('POWERBI_REPORT_ID'),

    // Azure AD OAuth endpoints
    'authority_url' => 'https://login.microsoftonline.com/' . env('POWERBI_TENANT_ID'),
    'token_url' => 'https://login.microsoftonline.com/' . env('POWERBI_TENANT_ID') . '/oauth2/v2.0/token',
    'scope' => 'https://analysis.windows.net/powerbi/api/.default',

    // Power BI REST API endpoints
    'api_url' => 'https://api.powerbi.com/v1.0/myorg',

    // Token cache duration (in minutes)
    'token_cache_duration' => 50, // Access tokens expire after 60 minutes

    // Embed URL
    'embed_url_base' => 'https://app.powerbi.com/reportEmbed',

];
