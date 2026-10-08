<?php
use App\Http\Controllers\Api\V1\Operations\PackageController;
use App\Support\Permissions;



Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {
    // Declared before /packages/{package} so "summary" and "order" are not bound as a package.
    Route::get('/packages/summary', [PackageController::class, 'summary'])->middleware('permission:' . Permissions::PACKAGES_VIEW);
    Route::put('/packages/order', [PackageController::class, 'reorder'])->middleware('permission:' . Permissions::PACKAGES_UPDATE);

    Route::get('/packages', [PackageController::class, 'index'])->middleware('permission:' . Permissions::PACKAGES_VIEW);
    Route::post('/packages', [PackageController::class, 'store'])->middleware('permission:' . Permissions::PACKAGES_CREATE);
    Route::post('/packages/{package}/duplicate', [PackageController::class, 'duplicate'])->middleware('permission:' . Permissions::PACKAGES_CREATE);
    Route::get('/packages/{package}', [PackageController::class, 'show'])->middleware('permission:' . Permissions::PACKAGES_VIEW);
    Route::patch('/packages/{package}', [PackageController::class, 'update'])->middleware('permission:' . Permissions::PACKAGES_UPDATE);
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->middleware('permission:' . Permissions::PACKAGES_DELETE);

});
