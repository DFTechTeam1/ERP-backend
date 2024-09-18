<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('ilham', function () {
    $service = new \App\Services\Geocoding();

    $response = $service->getCoordinate('Kota Surabaya, Jawa Timur');

    return $response;
});
