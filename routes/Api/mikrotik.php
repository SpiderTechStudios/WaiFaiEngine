<?php

use App\Http\Controllers\Api\MikroTik\MikroTikController;
use Illuminate\Support\Facades\Route;

/*
| MikroTik external captive portal endpoints (no user auth).
|
| The router's hotspot login.html redirects the browser to /api/mikrotik/login.
| That endpoint creates a pending captive session and sends the browser to the
| same /connect portal used by Ruijie/WiFiDog.
*/
Route::prefix('mikrotik')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('/login', [MikroTikController::class, 'login']);
        Route::get('/login.html', [MikroTikController::class, 'loginTemplate']);
        Route::match(['get', 'post'], '/ping', [MikroTikController::class, 'ping']);
        Route::post('/callback', [MikroTikController::class, 'callback']);
    });
