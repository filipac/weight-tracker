<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Withings API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Withings API integration
    |
    */

    'access_token' => env('WITHINGS_ACCESS_TOKEN'),
    'refresh_token' => env('WITHINGS_REFRESH_TOKEN'),
    'client_id' => env('WITHINGS_CLIENT_ID'),
    'client_secret' => env('WITHINGS_CLIENT_SECRET'),
    'base_url' => env('WITHINGS_BASE_URL', 'https://wbsapi.withings.net'),

    /*
    |--------------------------------------------------------------------------
    | OAuth URLs
    |--------------------------------------------------------------------------
    */
    'authorize_url' => 'https://account.withings.com/oauth2_user/authorize2',
    'token_url' => 'https://wbsapi.withings.net/v2/oauth2',

    /*
    |--------------------------------------------------------------------------
    | Default Scopes
    |--------------------------------------------------------------------------
    */
    'scopes' => [
        'user.info',
        'user.metrics',
        'user.activity',
    ],
];
