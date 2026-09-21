<?php

use App\Http\Controllers\Api\WiFiDog\WiFiDogController;
use App\Http\Controllers\Web\CaptivePortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Backend-hosted captive portal (single page).
|
| The WiFiDog login endpoint redirects the browser here with:
|   /connect?subdomain={company}&router={router}&session={captive-token}
|
| All UI states (initial / subscribe / voucher / redeem) are switched with
| JavaScript on this one page; no separate routes or pages.
|
| The WiFiDog protocol endpoints themselves live in routes/Api/wifidog.php
| (/api/wifidog/login, /auth, /portal, /ping).
*/
Route::get('/connect', [CaptivePortalController::class, 'show'])->name('captive.connect');

Route::prefix('wifidog')
    ->middleware('throttle:120,1')
    ->group(function () {
        // Ruijie builds call both /login and /login/? — register both.
        foreach (['/login', '/login/', '/auth', '/auth/', '/portal', '/portal/', '/ping', '/ping/'] as $path) {
            $action = trim($path, '/');
            Route::match(['get', 'post'], $path, [WiFiDogController::class, $action]);
        }
    });

    