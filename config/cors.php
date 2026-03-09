<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => [
        'https://app.definepress.com',
        'https://definepress.com',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],
    
    'allowed_origins_patterns' => [],
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => [],
    
    'max_age' => 7200,  // Cache preflight for 2 hours
    
    'supports_credentials' => true,  // CRITICAL for Sanctum cookies
];