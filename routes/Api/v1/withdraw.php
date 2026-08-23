<?php

use App\Http\Controllers\Api\V1\Operations\WithdrawalController;
use App\Support\Permissions;



Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {

    Route::get('/withdrawals/stats', [WithdrawalController::class, 'stats'])->middleware('permission:' . Permissions::WITHDRAWALS_VIEW);
    Route::get('/withdrawals', [WithdrawalController::class, 'index'])->middleware('permission:' . Permissions::WITHDRAWALS_VIEW);
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])->middleware('permission:' . Permissions::WITHDRAWALS_CREATE);
    Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->middleware('permission:' . Permissions::WITHDRAWALS_VIEW);


});