<?php

use App\Http\Controllers\Api\WiFiDog\WiFiDogController;
use Illuminate\Support\Facades\Route;

/*
| Production WiFiDog / Ruijie Reyee hotspot endpoints (no user auth).
|
| These are the ONLY WiFiDog endpoints. They are called by the Ruijie RAP62-OD
| (ReyeeOS) external captive portal.
|
| Ruijie configures a single Portal Server URL "base" and appends a script
| fragment to it, so the base must be:
|
|   https://waifai.shereheyangu.com/api/wifidog
|
| which the gateway then extends to:
|
|   <base>/login   browser -> authentication portal (NO authorization here)
|   <base>/auth    AP server-to-server -> "Auth:1" / "Auth:0" (plain text)
|   <base>/portal  browser -> post-auth result / ?message=denied failure page
|   <base>/ping    AP heartbeat -> "Pong" (plain text)
|
| Do not point the AP base at .../api/wifidog/login: that produces doubled
| paths (login/login) and the server-to-server /auth leg never reaches us.
*/
Route::prefix('wifidog')
    ->group(function () {
        // Browser-facing legs: keep a per-IP throttle.
        Route::match(['get', 'post'], '/login', [WiFiDogController::class, 'login'])
            ->middleware('throttle:120,1');
        Route::match(['get', 'post'], '/portal', [WiFiDogController::class, 'portal'])
            ->middleware('throttle:120,1');

        // Server-to-server legs called by the gateway for every connected
        // client (auth checks/counters and heartbeat). Throttling these makes
        // the AP receive 429s and drop/flap authorized clients, so they are
        // intentionally not rate limited.
        Route::match(['get', 'post'], '/auth', [WiFiDogController::class, 'auth']);
        Route::match(['get', 'post'], '/ping', [WiFiDogController::class, 'ping']);
    });
