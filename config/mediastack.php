<?php

return [
    'api_key' => env('MEDIASTACK_API_KEY'),
    'api_url' => env('MEDIASTACK_API_URL', 'http://api.mediastack.com/v1/news'),
    'https' => env('MEDIASTACK_HTTPS', true),
    'rate_limit' => env('MEDIASTACK_RATE_LIMIT', 1000),
    'timeout' => env('MEDIASTACK_TIMEOUT', 30),
    
    'default_params' => [
        'limit' => 100,
        'languages' => 'en',
        'countries' => 'us,gb,ca,au',
        'categories' => 'general,business,entertainment,health,science,sports,technology',
        'sort' => 'published_desc',
    ],
    
    'retry' => [
        'times' => 3,
        'sleep' => 1000, // milliseconds
    ],
];
