<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Untuk produksi, sebaiknya ganti '*' dengan domain web app yang spesifik,
    // misal ['https://aset-gudang-web.vercel.app']
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
