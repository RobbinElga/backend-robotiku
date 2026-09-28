<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration — RobotiKU API
    |--------------------------------------------------------------------------
    | Mengizinkan frontend Next.js (FRONTEND_URL) mengakses API.
    | Tambahkan domain produksi nanti di array 'allowed_origins'.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        'trim',
        explode(',', env('FRONTEND_URL', 'http://localhost:3000'))
    )))),

    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)*robotiku\.id$#i',
    ],

    'allowed_headers' => ['*', 'Authorization', 'Content-Type', 'X-Requested-With', 'Accept', 'Origin'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer-token auth tidak butuh cookie credential, tapi kita aktifkan
    // supaya aman bila nanti pakai mode stateful/cookie.
    'supports_credentials' => true,

];
