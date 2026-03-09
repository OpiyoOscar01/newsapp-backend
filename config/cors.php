<?php
return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://definepress.com',
        'https://app.definepress.com',
        'https://www.definepress.com',
        'https://www.app.definepress.com',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // If you are using Sanctum SPA cookie auth => true
    // If you are ONLY using Bearer tokens => can be false
    'supports_credentials' => false,
];
