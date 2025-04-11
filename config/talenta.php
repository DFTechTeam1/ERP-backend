<?php

return [
    'client_id' => env("MEKARI_MODE") == 'sandbox' ? env('MEKARI_CLIENT_ID') : env('MEKARI_CLIENT_ID_LIVE'),
    'client_secret' => env("MEKARI_MODE") == 'sandbox' ? env('MEKARI_CLIENT_SECRET') : env('MEKARI_CLIENT_SECRET_LIVE'),
    'base_uri' => env('MEKARI_MODE') == 'sandbox' ? env('MEKARI_SANDBOX_URL') : env('MEKARI_PRODUCTION_URL'),
    'prod_uri' => env('MEKARI_PRODUCTION_URL'),
    'dev_uri' => env('MEKARI_SANDBOX_URL'),

    'endpoint_list' => [
        'detail_employee' => '/v2/talenta/v3/employees',
        'timeoff_list' => '/v2/talenta/v2/time-off',
        'all_employees' => '/v2/talenta/v2/employee'
    ],

    'endpoint_method' => [
        'detail_employee' => 'get',
        'timeoff_list' => 'get',
        'all_employees' => 'get'
    ]
 ];
