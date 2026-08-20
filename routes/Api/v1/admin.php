<?php

use App\Http\Controllers\Api\V1\Company\StaffController;
use App\Http\Controllers\Api\V1\Operations\BranchController;
use App\Http\Controllers\Api\V1\Operations\CustomerController;
use App\Http\Controllers\Api\V1\Operations\DashboardController;
use App\Http\Controllers\Api\V1\Operations\HotspotSessionController;
use App\Http\Controllers\Api\V1\Operations\IncomeController;
use App\Http\Controllers\Api\V1\Operations\PaymentController;
use App\Http\Controllers\Api\V1\Operations\SettingsController;
use App\Http\Controllers\Api\V1\Operations\VoucherController;
use App\Http\Controllers\Api\V1\Operations\WithdrawalController;
use App\Http\Controllers\Api\V1\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\Api\V1\SuperAdmin\UserController as SuperAdminUserController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->middleware('permission:'.Permissions::DASHBOARD_VIEW);
    Route::get('/income', [IncomeController::class, 'index'])->middleware('permission:'.Permissions::INCOME_VIEW);

    Route::get('/vouchers', [VoucherController::class, 'index'])->middleware('permission:'.Permissions::VOUCHERS_VIEW);
    Route::get('/vouchers/batches', [VoucherController::class, 'batches'])->middleware('permission:'.Permissions::VOUCHERS_VIEW);
    Route::post('/vouchers', [VoucherController::class, 'store'])->middleware('permission:'.Permissions::VOUCHERS_CREATE);

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:'.Permissions::PAYMENTS_VIEW);
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:'.Permissions::PAYMENTS_CREATE);
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:'.Permissions::PAYMENTS_VIEW);

    Route::get('/sessions', [HotspotSessionController::class, 'index'])->middleware('permission:'.Permissions::SESSIONS_VIEW);
    Route::get('/sessions/{session}', [HotspotSessionController::class, 'show'])->middleware('permission:'.Permissions::SESSIONS_VIEW);

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:'.Permissions::CUSTOMERS_VIEW);
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:'.Permissions::CUSTOMERS_VIEW);

    Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:'.Permissions::STAFF_VIEW);
    Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:'.Permissions::STAFF_CREATE);
    Route::get('/staff/{membership}', [StaffController::class, 'show'])->middleware('permission:'.Permissions::STAFF_VIEW);
    Route::patch('/staff/{membership}/role', [StaffController::class, 'updateRole'])->middleware('permission:'.Permissions::STAFF_CHANGE_ROLE);
    Route::patch('/staff/{membership}', [StaffController::class, 'updateRole'])->middleware('permission:'.Permissions::STAFF_CHANGE_ROLE);
    Route::patch('/staff/{membership}/suspend', [StaffController::class, 'suspend'])->middleware('permission:'.Permissions::STAFF_SUSPEND);
    Route::patch('/staff/{membership}/activate', [StaffController::class, 'activate'])->middleware('permission:'.Permissions::STAFF_UPDATE);
    Route::delete('/staff/{membership}', [StaffController::class, 'destroy'])->middleware('permission:'.Permissions::STAFF_DELETE);
    Route::post('/ownership/transfer', [StaffController::class, 'transferOwnership'])->middleware('permission:'.Permissions::OWNERSHIP_TRANSFER);

    Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:'.Permissions::BRANCHES_VIEW);
    Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:'.Permissions::BRANCHES_CREATE);
    Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('permission:'.Permissions::BRANCHES_VIEW);
    Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:'.Permissions::BRANCHES_UPDATE);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:'.Permissions::BRANCHES_DELETE);

    Route::get('/withdrawals', [WithdrawalController::class, 'index'])->middleware('permission:'.Permissions::WITHDRAWALS_VIEW);
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])->middleware('permission:'.Permissions::WITHDRAWALS_CREATE);
    Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->middleware('permission:'.Permissions::WITHDRAWALS_VIEW);

    Route::get('/settings', [SettingsController::class, 'show'])->middleware('permission:'.Permissions::SETTINGS_VIEW);
    Route::patch('/settings', [SettingsController::class, 'update'])->middleware('permission:'.Permissions::SETTINGS_UPDATE);
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
