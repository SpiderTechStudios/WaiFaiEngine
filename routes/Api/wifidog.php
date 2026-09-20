<?php

use App\Http\Controllers\Api\WiFiDog\WiFiDogController;
use App\Http\Controllers\TestWifiDogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
|
| After the gateway authorizes the client it 302-redirects the browser to the
| auth server's portal/ script (GET /api/wifidog/portal?gw_id=...&token=...).
| We answer with a redirect to the originally requested URL (or CAPTIVE_PORTAL_SUCCESS_URL)
| so the customer lands on their destination instead of a 404.
*/
// Route::prefix('wifidog')
//     ->middleware('throttle:120,1')
//     ->group(function () {
//         Route::get('/', [WiFiDogController::class, 'login']);
//         Route::get('/login', [WiFiDogController::class, 'login']);
//         Route::get('/auth', [WiFiDogController::class, 'auth']);
//         Route::get('/portal', [WiFiDogController::class, 'portal']);
//         Route::get('/ping', [WiFiDogController::class, 'ping']);

//         // Compatibility: gateways whose AuthServer Path mistakenly includes
//         // "login" (e.g. Path=/api/wifidog/login/) call /login/auth, /login/ping,
//         // etc. Route those to the correct handlers so the client can still be
//         // authorized even while the AP config is wrong.
//         Route::get('/login/login', [WiFiDogController::class, 'login']);
//         Route::get('/login/auth', [WiFiDogController::class, 'auth']);
//         Route::get('/login/portal', [WiFiDogController::class, 'portal']);
//         Route::get('/login/ping', [WiFiDogController::class, 'ping']);

//         // Diagnostics: log any path the Ruijie AP calls that we don't handle,
//         // so a wrong AuthServer Path / script fragment shows up in the log.
//         Route::any('/{path}', function (Request $request, string $path) {
//             Log::warning('wifidog.unhandled_route', [
//                 'path' => $path,
//                 'method' => $request->method(),
//                 'query' => $request->query(),
//                 'user_agent' => $request->userAgent(),
//             ]);

//             return response('Auth: 0', 200)->header('Content-Type', 'text/plain');
//         })->where('path', '.*');
//     });


Route::prefix('wifidog')->group(function () {
    Route::get('/login', [TestWifiDogController::class, 'login']);
    Route::get('/auth', [TestWifiDogController::class, 'auth']);
    Route::get('/portal', [TestWifiDogController::class, 'portal']);
    Route::get('/portal/accept', [TestWifiDogController::class, 'accept']);
    Route::get('/ping', [TestWifiDogController::class, 'ping']);

    // End-to-end guarantee test (legacy/flow.png): login -> granted -> verify -> portal.
    Route::get('/test', [TestWifiDogController::class, 'test']);
});
