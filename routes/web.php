<?php

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
