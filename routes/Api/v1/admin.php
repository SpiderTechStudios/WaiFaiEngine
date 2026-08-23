<?php

use App\Http\Controllers\Api\V1\Admin\AdminRouterController;
use App\Http\Controllers\Api\V1\Operations\PaymentController;
use App\Http\Controllers\Api\V1\Operations\WithdrawalController;
use App\Http\Controllers\Api\V1\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\Api\V1\SuperAdmin\UserController as SuperAdminUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:sanctum', 'active.user', 'platform.admin'])->group(function () {
    Route::get('/routers', [AdminRouterController::class, 'index']);
    Route::post('/routers', [AdminRouterController::class, 'store']);
    Route::get('/routers/{router}', [AdminRouterController::class, 'show']);
    Route::patch('/routers/{router}', [AdminRouterController::class, 'update']);
    Route::delete('/routers/{router}', [AdminRouterController::class, 'destroy']);
    Route::post('/routers/{router}/sync', [AdminRouterController::class, 'sync']);
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

    Route::get('/payments', [PaymentController::class, 'getAllPayments']);
    Route::get('/payments/{payment}', [PaymentController::class, 'getPayment']);

    Route::get('/withdrawals', [WithdrawalController::class, 'adminGetWithdrawals']);
    Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'adminGetWithdrawal']);
});
