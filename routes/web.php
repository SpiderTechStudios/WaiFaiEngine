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
| WiFiDog auth-server HTTP interface (exactly per the WiFiDog protocol spec):
|
|   GET /login   browser  -> 302 to http://{gw_address}:{gw_port}/wifidog/auth?token=...&url=...
|   GET /auth    AP (s2s) -> "Auth: 1" / "Auth: 0" (text/plain)
|   GET /ping    AP (s2s) -> "Pong"
|   GET /portal  browser  -> 302 (post-auth / message landing)
|
| Also exposed under /api/wifidog/* and /wifidog/* so any gateway base-path
| configuration reaches the same handlers.
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
});
