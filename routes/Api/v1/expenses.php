<?php

use App\Http\Controllers\Api\V1\Operations\ExpenseController;
use App\Http\Controllers\Api\V1\Operations\ExpenseTypeController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {
    // Declared before /expenses/{expense} so "summary" is not bound as an expense.
    Route::get('/expenses/summary', [ExpenseController::class, 'summary'])->middleware('permission:'.Permissions::EXPENSES_VIEW);

    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:'.Permissions::EXPENSES_VIEW);
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:'.Permissions::EXPENSES_CREATE);
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->middleware('permission:'.Permissions::EXPENSES_VIEW);
    Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:'.Permissions::EXPENSES_UPDATE);
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:'.Permissions::EXPENSES_UPDATE);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:'.Permissions::EXPENSES_DELETE);

    // Read-only catalog of active expense types for company users.
    Route::get('/expense-types', [ExpenseTypeController::class, 'index'])->middleware('permission:'.Permissions::EXPENSES_VIEW);
});
