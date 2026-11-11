<?php

use App\Http\Controllers\Api\V1\Operations\OmadaPortalController;
use Illuminate\Support\Facades\Route;

/*
| Omada external captive-portal endpoints. Omada appends clientMac, clientIp,
| apMac, ssidName, site, radioId, redirectUrl and t to the URL configured on the
| SSID. These endpoints are public because the client is not authenticated yet.
*/
Route::prefix('omada/portal/{key}')
    ->middleware('throttle:60,1')
    ->group(function () {
        Route::get('/', [OmadaPortalController::class, 'entry']);
        Route::post('/authorize', [OmadaPortalController::class, 'authorize'])->middleware('throttle:30,1');
        Route::post('/disconnect', [OmadaPortalController::class, 'disconnect'])->middleware('throttle:30,1');
    });
