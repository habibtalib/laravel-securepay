<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing or 'production' for live payments.
    |
    */
    'environment' => env('SECUREPAY_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Get your credentials from SecurePay Console → Developer Tools → API Credentials
    | https://console.securepay.my
    |
    */
    'credentials' => [
        'production' => [
            'client_id' => env('SECUREPAY_CLIENT_ID'),
            'client_secret' => env('SECUREPAY_CLIENT_SECRET'),
        ],
        'sandbox' => [
            'client_id' => env('SECUREPAY_SANDBOX_CLIENT_ID'),
            'client_secret' => env('SECUREPAY_SANDBOX_CLIENT_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Base URLs
    |--------------------------------------------------------------------------
    */
    'urls' => [
        'production' => 'https://console.securepay.my/api',
        'sandbox' => 'https://sandbox.securepay.dev/api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback & Redirect URLs
    |--------------------------------------------------------------------------
    |
    | callback_url: SecurePay will POST payment status here (server-to-server)
    | redirect_url: Customer is redirected here after payment
    |
    */
    'callback_url' => env('SECUREPAY_CALLBACK_URL'),
    'redirect_url' => env('SECUREPAY_REDIRECT_URL'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | Cache store and key prefix for JWT tokens.
    |
    */
    'cache' => [
        'store' => env('SECUREPAY_CACHE_STORE', null), // null = default store
        'prefix' => 'securepay_',
        'ttl_buffer' => 60, // seconds before expiry to refresh
    ],

];
