<?php

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminExpenseTypeController;
use App\Http\Controllers\Api\V1\Admin\AdminInstallationRequestController;
use App\Http\Controllers\Api\V1\Admin\AdminRouterController;
use App\Http\Controllers\Api\V1\Operations\PaymentController;
use App\Http\Controllers\Api\V1\Operations\WithdrawalController;
use App\Http\Controllers\Api\V1\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\Api\V1\SuperAdmin\EnrollmentController as SuperAdminEnrollmentController;
use App\Http\Controllers\Api\V1\SuperAdmin\PaymentProviderController;
use App\Http\Controllers\Api\V1\SuperAdmin\UserController as SuperAdminUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:sanctum', 'active.user', 'platform.admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'show']);

    Route::get('/routers', [AdminRouterController::class, 'index']);
    Route::post('/routers', [AdminRouterController::class, 'store']);
    Route::get('/routers/{router}', [AdminRouterController::class, 'show']);
    Route::patch('/routers/{router}', [AdminRouterController::class, 'update']);
    Route::delete('/routers/{router}', [AdminRouterController::class, 'destroy']);
    Route::post('/routers/{router}/sync', [AdminRouterController::class, 'sync']);

    Route::get('/installation-requests', [AdminInstallationRequestController::class, 'index']);
    Route::get('/installation-requests/{installationRequest}', [AdminInstallationRequestController::class, 'show']);
    Route::patch('/installation-requests/{installationRequest}/fulfillment', [AdminInstallationRequestController::class, 'updateFulfillment']);
    Route::post('/installation-requests/{installationRequest}/updates', [AdminInstallationRequestController::class, 'addUpdate']);

    Route::get('/expense-types', [AdminExpenseTypeController::class, 'index']);
    Route::post('/expense-types', [AdminExpenseTypeController::class, 'store']);
    Route::get('/expense-types/{expenseType}', [AdminExpenseTypeController::class, 'show']);
    Route::patch('/expense-types/{expenseType}', [AdminExpenseTypeController::class, 'update']);
    Route::put('/expense-types/{expenseType}', [AdminExpenseTypeController::class, 'update']);
    Route::delete('/expense-types/{expenseType}', [AdminExpenseTypeController::class, 'destroy']);
    Route::post('/expense-types/{expenseType}/activate', [AdminExpenseTypeController::class, 'activate']);
    Route::post('/expense-types/{expenseType}/deactivate', [AdminExpenseTypeController::class, 'deactivate']);
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

    Route::get('/enrollments', [SuperAdminEnrollmentController::class, 'index']);
    Route::get('/enrollments/{enrollment}', [SuperAdminEnrollmentController::class, 'show']);

    Route::get('/payments', [PaymentController::class, 'getAllPayments']);
    Route::get('/payments/{payment}', [PaymentController::class, 'getPayment']);

    Route::get('/withdrawals', [WithdrawalController::class, 'adminGetWithdrawals']);
    Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'adminGetWithdrawal']);

    Route::get('/payment-providers', [PaymentProviderController::class, 'index']);
    Route::post('/payment-providers', [PaymentProviderController::class, 'store']);
    Route::get('/payment-providers/{paymentProvider}', [PaymentProviderController::class, 'show']);
    Route::patch('/payment-providers/{paymentProvider}', [PaymentProviderController::class, 'update']);
    Route::delete('/payment-providers/{paymentProvider}', [PaymentProviderController::class, 'destroy']);
    Route::post('/payment-providers/{paymentProvider}/enable', [PaymentProviderController::class, 'enable']);
    Route::post('/payment-providers/{paymentProvider}/disable', [PaymentProviderController::class, 'disable']);
    Route::post('/payment-providers/{paymentProvider}/default-payments', [PaymentProviderController::class, 'setDefaultPayments']);
    Route::post('/payment-providers/{paymentProvider}/default-payouts', [PaymentProviderController::class, 'setDefaultPayouts']);
});
