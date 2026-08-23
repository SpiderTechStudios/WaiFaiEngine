<?php

use App\Http\Controllers\Api\V1\Operations\VoucherController;
use App\Support\Permissions;


Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {



    Route::get('/vouchers', [VoucherController::class, 'index'])->middleware('permission:' . Permissions::VOUCHERS_VIEW);
    Route::post('/vouchers', [VoucherController::class, 'store'])->middleware('permission:' . Permissions::VOUCHERS_CREATE);
    Route::post('/vouchers/{voucher}/revoke', [VoucherController::class, 'revoke'])->middleware('permission:' . Permissions::VOUCHERS_REVOKE);
    Route::post('/vouchers/{voucher}/consume', [VoucherController::class, 'consume'])->middleware('permission:' . Permissions::VOUCHERS_CONSUME);

});