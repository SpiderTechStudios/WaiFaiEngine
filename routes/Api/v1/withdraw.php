<?php

use App\Http\Controllers\Api\V1\Operations\PayoutAccountController;
use App\Http\Controllers\Api\V1\Operations\WithdrawalController;
use App\Support\Permissions;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {
    Route::get('/withdrawals/stats', [WithdrawalController::class, 'stats'])->middleware('permission:'.Permissions::WITHDRAWALS_VIEW);
    Route::post('/withdrawals/preview', [WithdrawalController::class, 'preview'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);

    Route::get('/withdrawals/payout-accounts', [PayoutAccountController::class, 'index'])->middleware('permission:'.Permissions::WITHDRAWALS_VIEW);
    Route::post('/withdrawals/payout-accounts', [PayoutAccountController::class, 'store'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
    Route::patch('/withdrawals/payout-accounts/{payoutAccount}', [PayoutAccountController::class, 'update'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
    Route::delete('/withdrawals/payout-accounts/{payoutAccount}', [PayoutAccountController::class, 'destroy'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
    Route::post('/withdrawals/payout-accounts/{payoutAccount}/default', [PayoutAccountController::class, 'setDefault'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);

    Route::get('/withdrawals', [WithdrawalController::class, 'index'])->middleware('permission:'.Permissions::WITHDRAWALS_VIEW);
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
    Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->middleware('permission:'.Permissions::WITHDRAWALS_VIEW);
    Route::post('/withdrawals/{withdrawal}/confirm', [WithdrawalController::class, 'confirm'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
    Route::post('/withdrawals/{withdrawal}/resend-otp', [WithdrawalController::class, 'resendOtp'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
    Route::delete('/withdrawals/{withdrawal}', [WithdrawalController::class, 'cancel'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
});
