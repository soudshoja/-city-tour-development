<?php

return [
    'base_url'    => env('CYGNET_BASE_URL', 'https://admin.cygnet-ims.com'),
    'username'    => env('CYGNET_USERNAME'),
    'password'    => env('CYGNET_PASSWORD'),
    'tenant'      => env('CYGNET_TENANT', '126'),
    'supplier_id' => (int) env('CYGNET_SUPPLIER_ID', 27),
    'company_id'  => (int) env('CYGNET_COMPANY_ID', 1),
    'create_from' => env('CYGNET_CREATE_FROM', '2026-05-30'),
    'zones' => [
        'AAA'      => 'Worldwide',
        'WELUCAJP' => 'Worldwide excluding country of residence, US, CA, AU, JP',
    ],
    'sync_key' => env('CYGNET_SYNC_KEY'),
];
