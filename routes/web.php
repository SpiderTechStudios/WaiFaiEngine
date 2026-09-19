<?php

use App\Http\Controllers\Web\CaptivePortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Backend-hosted captive portal.
|
| The WiFiDog login redirect sends the browser here with:
|   /connect?subdomain={company}&router={router}&session={captive-token}
|
| Kept on the backend (same host as the API) so the whole
| device → AP → portal → payment → auth flow can be traced in one place.
*/
Route::get('/connect', [CaptivePortalController::class, 'show'])->name('captive.connect');
Route::post('/connect/pay', [CaptivePortalController::class, 'pay'])->name('captive.pay');
Route::get('/connect/payment/{payment}', [CaptivePortalController::class, 'payment'])->name('captive.payment');
Route::post('/connect/voucher', [CaptivePortalController::class, 'voucher'])->name('captive.voucher');
