<?php

use Illuminate\Support\Facades\Route;

header('Access-Control-Allow-Origin:  *');
header('Access-Control-Allow-Methods:  POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers:  Content-Type, X-Auth-Token, Origin, Authorization');

Route::get('/', function () {
    return view('welcome');
});

Route::get('ilham', function () {
    return 'oke';
    $user = \App\Models\User::latest()->first();
    return (new \Modules\Hrd\Notifications\UserEmailActivation($user, 'iiejrkejrer', 'password'));
});
