<?php

use App\Http\Controllers\Api\V1\Operations\BranchController;
use App\Support\Permissions;


Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {

    Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:' . Permissions::BRANCHES_VIEW);
    Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:' . Permissions::BRANCHES_CREATE);
    Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('permission:' . Permissions::BRANCHES_VIEW);
    Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:' . Permissions::BRANCHES_UPDATE);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:' . Permissions::BRANCHES_DELETE);
});