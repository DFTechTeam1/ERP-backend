<?php

return [
    'client_id' => env('MEKARI_CLIENT_ID'),
    'client_secret' => env('MEKARI_CLIENT_SECRET'),
    'base_uri' => env('MEKARI_MODE') == 'sandbox' ? env('MEKARI_SANDBOX_URL') : env('MEKARI_PRODUCTION_URL'),

    'endpoint_list' => [
        'detail_employee' => '/v2/talenta/v3/employees',
        'all_employee' => '/v2/talenta/v3/employees',
        'store_employee' => '/v2/talenta/v2/employee'
    ],

    'endpoint_method' => [
        'detail_employee' => 'get',
        'all_employee' => 'get',
        'store_employee' => 'post'
    ]
 ];
