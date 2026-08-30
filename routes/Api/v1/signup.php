<?php

use App\Http\Controllers\Api\V1\Signup\SignupController;
use Illuminate\Support\Facades\Route;

Route::prefix('signup')->group(function () {
    Route::post('/intents', [SignupController::class, 'storeIntent'])
        ->middleware('throttle:10,1');

    Route::post('/intents/{intent}/payments', [SignupController::class, 'storePayment'])
        ->middleware('throttle:20,1');

    Route::get('/intents/{intent}/payments/{payment}', [SignupController::class, 'showPayment'])
        ->middleware('throttle:60,1');

    Route::post('/intents/{intent}/complete', [SignupController::class, 'complete'])
        ->middleware('throttle:10,1');
});
