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
| Ruijie Reyee WiFiDog Hotspot API (spec-correct endpoints).
|
|   GET /login   browser -> portal (payment / connect page)
|   GET /auth    AP (s2s) -> {"auth":1|0} for Ruijie APs, else "Auth: 1|0"
|   GET /ping    AP (s2s) -> Pong
|   GET /portal  browser -> post-auth / ?message=denied landing
|
| Exposed at the root (base = https://host), under /wifidog/* and /api/wifidog/*
| so any configured ServiceURL base reaches the same handlers.
*/
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/login', [WiFiDogController::class, 'login']);
    Route::get('/auth', [WiFiDogController::class, 'auth']);
    Route::get('/ping', [WiFiDogController::class, 'ping']);
    Route::get('/portal', [WiFiDogController::class, 'portal']);

    Route::get('/wifidog', [WiFiDogController::class, 'login']);
    Route::get('/wifidog/login', [WiFiDogController::class, 'login']);
    Route::get('/wifidog/auth', [WiFiDogController::class, 'auth']);
    Route::get('/wifidog/portal', [WiFiDogController::class, 'portal']);
    Route::get('/wifidog/ping', [WiFiDogController::class, 'ping']);

    // Compatibility for a ServiceURL base that includes /login.
    Route::get('/login/login', [WiFiDogController::class, 'login']);
    Route::get('/login/auth', [WiFiDogController::class, 'auth']);
    Route::get('/login/portal', [WiFiDogController::class, 'portal']);
    Route::get('/login/ping', [WiFiDogController::class, 'ping']);
});
