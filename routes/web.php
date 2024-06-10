<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('ilham', function () {
    return 'oke';
    $user = \App\Models\User::latest()->first();
    return (new \Modules\Hrd\Notifications\UserEmailActivation($user, 'iiejrkejrer', 'password'));
});
