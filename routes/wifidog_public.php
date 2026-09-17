<?php

use App\Http\Controllers\Api\WiFiDog\WiFiDogController;
use Illuminate\Support\Facades\Route;

/*
| Some Ruijie/Reyee deployments point the WiFiDog Portal Server URL at
| https://{api_host}/public/api/wifidog/... (the physical Laravel "public"
| directory). Depending on the web server docroot that prefix may reach PHP
| unchanged. These aliases resolve login/auth/portal/ping in that case and
| avoid a redirect hop (a redirect here is what feeds the redirect loop).
*/
Route::prefix('public/api/wifidog')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('/login', [WiFiDogController::class, 'login']);
        Route::get('/auth', [WiFiDogController::class, 'auth']);
        Route::get('/portal', [WiFiDogController::class, 'portal']);
        Route::get('/ping', [WiFiDogController::class, 'ping']);
    });
