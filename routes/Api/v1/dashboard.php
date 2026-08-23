<?php

use App\Http\Controllers\Api\V1\Operations\DashboardController;
use App\Http\Controllers\Api\V1\Operations\IncomeController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])
        ->middleware('permission:'.Permissions::DASHBOARD_VIEW);

    Route::get('/income', [IncomeController::class, 'index'])
        ->middleware('permission:'.Permissions::INCOME_VIEW);
});
