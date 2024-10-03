<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('generate-official-email', [\App\Http\Controllers\Api\TestingController::class, 'generateOfficialEmail']);

Route::get('ilham', function () {
    $service = new \App\Services\Geocoding();

    $response = $service->getCoordinate('Kota Surabaya, Jawa Timur');

    return $response;
});

Route::get('login', function () {
    return view('auth.login');
})->name('login');
