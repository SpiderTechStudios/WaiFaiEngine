<?php

use App\Http\Controllers\Api\V1\Captive\CaptiveSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('captive')
    ->middleware(['throttle:60,1'])
    ->group(function () {
        Route::get('/sessions/{token}', [CaptiveSessionController::class, 'show'])
            ->where('token', '[A-Fa-f0-9]{32}');

        Route::post('/sessions/{token}/authorize', [CaptiveSessionController::class, 'authorize'])
            ->where('token', '[A-Fa-f0-9]{32}')
            ->middleware('throttle:30,1');
    });
