<?php

use App\Http\Controllers\Api\V1\Operations\HotspotSessionController;
use App\Support\Permissions;


Route::middleware(['auth:sanctum', 'active.user', 'company.context', 'company.required', 'subscription.active'])->group(function () {


    Route::get('/sessions', [HotspotSessionController::class, 'index'])->middleware('permission:' . Permissions::SESSIONS_VIEW);
    Route::post('/sessions', [HotspotSessionController::class, 'store'])->middleware('permission:' . Permissions::SESSIONS_CREATE);
    Route::get('/sessions/{session}', [HotspotSessionController::class, 'show'])->middleware('permission:' . Permissions::SESSIONS_VIEW);


});