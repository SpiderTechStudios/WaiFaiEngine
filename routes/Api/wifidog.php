<?php

use App\Http\Controllers\Api\WiFiDog\WiFiDogController;
use Illuminate\Support\Facades\Route;

/*
| WiFiDog / Ruijie gateway protocol endpoints (no user auth).
| Canonical paths used by gateways: /api/wifidog/*
*/
Route::prefix('wifidog')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('/login', [WiFiDogController::class, 'login']);
        Route::get('/auth', [WiFiDogController::class, 'auth']);
        Route::get('/ping', [WiFiDogController::class, 'ping']);
    });
