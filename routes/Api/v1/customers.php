<?php

use App\Http\Controllers\Api\V1\Operations\CustomerController;
use App\Support\Permissions;


Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:' . Permissions::CUSTOMERS_VIEW);
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:' . Permissions::CUSTOMERS_VIEW);

});