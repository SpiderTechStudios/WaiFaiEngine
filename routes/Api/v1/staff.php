<?php

use App\Http\Controllers\Api\V1\Company\StaffController;
use App\Support\Permissions;




Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {



    Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:' . Permissions::STAFF_VIEW);
    Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:' . Permissions::STAFF_CREATE);
    Route::get('/staff/{membership}', [StaffController::class, 'show'])->middleware('permission:' . Permissions::STAFF_VIEW);
    Route::patch('/staff/{membership}/role', [StaffController::class, 'updateRole'])->middleware('permission:' . Permissions::STAFF_CHANGE_ROLE);
    Route::patch('/staff/{membership}', [StaffController::class, 'updateRole'])->middleware('permission:' . Permissions::STAFF_CHANGE_ROLE);
    Route::patch('/staff/{membership}/suspend', [StaffController::class, 'suspend'])->middleware('permission:' . Permissions::STAFF_SUSPEND);
    Route::patch('/staff/{membership}/activate', [StaffController::class, 'activate'])->middleware('permission:' . Permissions::STAFF_UPDATE);
    Route::delete('/staff/{membership}', [StaffController::class, 'destroy'])->middleware('permission:' . Permissions::STAFF_DELETE);
    Route::post('/ownership/transfer', [StaffController::class, 'transferOwnership'])->middleware('permission:' . Permissions::OWNERSHIP_TRANSFER);


});