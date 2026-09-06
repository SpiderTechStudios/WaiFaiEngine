<?php

use App\Http\Controllers\Api\WiFiDog\WiFiDogController;
use Illuminate\Support\Facades\Route;

/*
| WiFiDog / Ruijie gateway protocol endpoints (no user auth).
|
| Canonical paths used by gateways: /api/wifidog/*
|
| IMPORTANT — Ruijie / WiFiDog AuthServer settings:
|   Hostname = waifai.shereheyangu.com
|   Path     = /api/wifidog/          ← must NOT include "login"
|   LoginScriptPathFragment = login/?  (default)
|
| WiFiDog concatenates: Path + LoginScriptPathFragment
|   Correct: /api/wifidog/ + login/?  => /api/wifidog/login/?gw_id=...
|   Wrong:   /api/wifidog/login/ + login/? => /api/wifidog/login/login/  (404)
|
| After login succeeds, Laravel 302-redirects the phone browser to CAPTIVE_PORTAL_URL
| (frontend /connect), never back into /api/wifidog/*.
*/
Route::prefix('wifidog')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('/login', [WiFiDogController::class, 'login']);
        Route::get('/auth', [WiFiDogController::class, 'auth']);
        Route::get('/ping', [WiFiDogController::class, 'ping']);
    });
