<?php

use App\Http\Controllers\Api\V1\SuperAdmin\BrandController;
use App\Http\Controllers\Api\V1\SuperAdmin\DeviceCategoryController;
use App\Http\Controllers\Api\V1\SuperAdmin\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('superadmin')
    ->middleware(['auth:sanctum', 'active.user', 'superadmin'])
    ->group(function () {
        Route::get('/brands', [BrandController::class, 'index']);
        Route::post('/brands', [BrandController::class, 'store']);
        Route::get('/brands/{brand}', [BrandController::class, 'show']);
        Route::match(['patch', 'post'], '/brands/{brand}', [BrandController::class, 'update']);
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy']);

        Route::get('/device-categories', [DeviceCategoryController::class, 'index']);
        Route::post('/device-categories', [DeviceCategoryController::class, 'store']);
        Route::get('/device-categories/{deviceCategory}', [DeviceCategoryController::class, 'show']);
        Route::patch('/device-categories/{deviceCategory}', [DeviceCategoryController::class, 'update']);
        Route::delete('/device-categories/{deviceCategory}', [DeviceCategoryController::class, 'destroy']);

        Route::get('/devices', [DeviceController::class, 'index']);
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::get('/devices/{device}', [DeviceController::class, 'show']);
        Route::match(['patch', 'post'], '/devices/{device}', [DeviceController::class, 'update']);
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);
    });
