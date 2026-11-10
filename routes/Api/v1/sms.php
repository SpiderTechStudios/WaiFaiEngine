<?php

use App\Http\Controllers\Api\V1\Sms\SmsController;
use Illuminate\Support\Facades\Route;

/*
| NextSMS integration:
| - Public delivery-report callback for NextSMS.
| - Superadmin-only send/test/broadcast endpoints.
|
| Docs: https://documenter.getpostman.com/view/4680389/SW7dX7JL
*/

Route::match(['get', 'post'], 'sms/delivery-report', [SmsController::class, 'deliveryReport']);

Route::prefix('superadmin/sms')->middleware(['auth:sanctum', 'active.user', 'superadmin'])->group(function () {
    Route::get('balance', [SmsController::class, 'balance']);
    Route::post('send', [SmsController::class, 'sendSingle']);
    Route::post('send-multiple', [SmsController::class, 'sendMultiple']);
    Route::post('broadcast', [SmsController::class, 'broadcast']);
});
