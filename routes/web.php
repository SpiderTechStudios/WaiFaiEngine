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
| The WiFiDog login redirect sends the browser here with:
|   /connect?subdomain={company}&router={router}&session={captive-token}
|
| All UI states (initial / subscribe / voucher / redeem) are switched with
| JavaScript on this one page; no separate routes or pages.
*/
Route::get('/connect', [CaptivePortalController::class, 'show'])->name('captive.connect');

/*
| WiFiDog gateway compatibility aliases.
|
| Gateways derive the auth/ping URL from their "Portal Server IP" + Path config.
| If the AP is configured with the wrong base path (e.g. / or /wifidog/), these
| aliases still let it reach the handler so the client can be authorized.
*/
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/auth', [WiFiDogController::class, 'auth']);
    Route::get('/ping', [WiFiDogController::class, 'ping']);
    Route::get('/wifidog', [WiFiDogController::class, 'login']);
    Route::get('/wifidog/login', [WiFiDogController::class, 'login']);
    Route::get('/wifidog/auth', [WiFiDogController::class, 'auth']);
    Route::get('/wifidog/portal', [WiFiDogController::class, 'portal']);
    Route::get('/wifidog/ping', [WiFiDogController::class, 'ping']);
});
