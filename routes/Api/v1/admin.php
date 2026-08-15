<?php

use App\Http\Controllers\Api\V1\Company\CompanyController;
use App\Http\Controllers\Api\V1\Company\StaffController;
use App\Http\Controllers\Api\V1\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\Api\V1\SuperAdmin\UserController as SuperAdminUserController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context'])->group(function () {
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->middleware('company.access');
    Route::patch('/companies/{company}', [CompanyController::class, 'update'])->middleware('company.access');

    Route::middleware(['company.access'])->group(function () {
        Route::get('/companies/{company}/staff', [StaffController::class, 'index'])->middleware('permission:'.Permissions::STAFF_VIEW);
        Route::post('/companies/{company}/staff', [StaffController::class, 'store'])->middleware('permission:'.Permissions::STAFF_CREATE);
        Route::get('/companies/{company}/staff/{membership}', [StaffController::class, 'show'])->middleware('permission:'.Permissions::STAFF_VIEW);
        Route::patch('/companies/{company}/staff/{membership}/role', [StaffController::class, 'updateRole'])->middleware('permission:'.Permissions::STAFF_CHANGE_ROLE);
        Route::patch('/companies/{company}/staff/{membership}', [StaffController::class, 'updateRole'])->middleware('permission:'.Permissions::STAFF_CHANGE_ROLE);
        Route::patch('/companies/{company}/staff/{membership}/suspend', [StaffController::class, 'suspend'])->middleware('permission:'.Permissions::STAFF_SUSPEND);
        Route::patch('/companies/{company}/staff/{membership}/activate', [StaffController::class, 'activate'])->middleware('permission:'.Permissions::STAFF_UPDATE);
        Route::delete('/companies/{company}/staff/{membership}', [StaffController::class, 'destroy'])->middleware('permission:'.Permissions::STAFF_DELETE);
        Route::post('/companies/{company}/ownership/transfer', [StaffController::class, 'transferOwnership'])->middleware('permission:'.Permissions::OWNERSHIP_TRANSFER);
    });
});

Route::prefix('superadmin')->middleware(['auth:sanctum', 'active.user', 'superadmin'])->group(function () {
    Route::get('/users', [SuperAdminUserController::class, 'index']);
    Route::get('/users/{user}', [SuperAdminUserController::class, 'show']);
    Route::patch('/users/{user}/suspend', [SuperAdminUserController::class, 'suspend']);
    Route::patch('/users/{user}/activate', [SuperAdminUserController::class, 'activate']);

    Route::get('/companies', [SuperAdminCompanyController::class, 'index']);
    Route::get('/companies/{company}', [SuperAdminCompanyController::class, 'show']);
    Route::patch('/companies/{company}/suspend', [SuperAdminCompanyController::class, 'suspend']);
    Route::patch('/companies/{company}/activate', [SuperAdminCompanyController::class, 'activate']);
});
