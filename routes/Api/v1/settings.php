<?php

use App\Http\Controllers\Api\V1\Operations\SettingsController;
use App\Support\Permissions;




Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required'])->group(function () {


    Route::get('/settings', [SettingsController::class, 'show'])->middleware('permission:' . Permissions::SETTINGS_VIEW);
    Route::patch('/settings', [SettingsController::class, 'update'])->middleware('permission:' . Permissions::SETTINGS_UPDATE);


});