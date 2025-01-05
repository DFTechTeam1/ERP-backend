<?php

return [
    'client_id' => env('MEKARI_CLIENT_ID'),
    'client_secret' => env('MEKARI_CLIENT_SECRET'),
    'base_uri' => env('MEKARI_MODE') == 'sandbox' ? env('MEKARI_SANDBOX_URL') : env('MEKARI_PRODUCTION_URL'),

    'endpoint_list' => [
        'detail_employee' => '/v2/talenta/v3/employees',
    ],

    'endpoint_method' => [
        'detail_employee' => 'get'
    ]
 ];