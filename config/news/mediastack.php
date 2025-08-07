<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MediaStack API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MediaStack News API integration
    |
    */

    'api_key' => env('MEDIASTACK_API_KEY'),
    'base_url' => env('MEDIASTACK_BASE_URL', 'http://api.mediastack.com/v1'),
    'timeout' => env('MEDIASTACK_TIMEOUT', 30),
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    
    'rate_limit' => [
        'requests_per_month' => env('MEDIASTACK_MONTHLY_LIMIT', 1000),
        'requests_per_hour' => env('MEDIASTACK_HOURLY_LIMIT', 100),
        'requests_per_day' => env('MEDIASTACK_DAILY_LIMIT', 1000),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Default Parameters
    |--------------------------------------------------------------------------
    */
    
    'defaults' => [
        'limit' => 100, // Maximum articles per request
        'languages' => ['en'], // Default languages
        'countries' => ['us', 'gb', 'ca', 'au'], // Default countries
        'categories' => [
            'general', 'business', 'entertainment', 'health',
            'science', 'sports', 'technology'
        ],
        'sort' => 'published_desc', // Default sorting
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    
    'cache' => [
        'enabled' => env('MEDIASTACK_CACHE_ENABLED', true),
        'ttl' => env('MEDIASTACK_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'mediastack',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Retry Logic
    |--------------------------------------------------------------------------
    */
    
    'retry' => [
        'max_attempts' => 3,
        'delay' => 1000, // milliseconds
        'exponential_backoff' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    
    'logging' => [
        'enabled' => env('MEDIASTACK_LOGGING_ENABLED', true),
        'level' => env('MEDIASTACK_LOG_LEVEL', 'info'),
        'channel' => env('MEDIASTACK_LOG_CHANNEL', 'stack'),
    ],
];