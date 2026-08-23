<?php


use App\Http\Controllers\Api\V1\Operations\PaymentController;
use App\Support\Permissions;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:' . Permissions::PAYMENTS_VIEW);
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:' . Permissions::PAYMENTS_CREATE);
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:' . Permissions::PAYMENTS_VIEW);

});