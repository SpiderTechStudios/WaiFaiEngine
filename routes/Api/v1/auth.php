<?php

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\EnrollmentController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegisterAdminController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentProviderWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\PlatformPaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'defaultPage'])->name('login');

Route::post('/auth/register', [EnrollmentController::class, 'register'])->middleware('throttle:10,1');
Route::get('/auth/enrollments/{reference}/payment-status', [EnrollmentController::class, 'paymentStatus'])
    ->middleware('throttle:60,1');
Route::post('/auth/enrollments/{reference}/retry-payment', [EnrollmentController::class, 'retryPayment'])
    ->middleware('throttle:20,1');

Route::post('/webhooks/payments/{provider}', [PaymentProviderWebhookController::class, 'payments'])
    ->middleware('throttle:120,1');
Route::post('/webhooks/payouts/{provider}', [PaymentProviderWebhookController::class, 'payouts'])
    ->middleware('throttle:120,1');

// Legacy bridge (prefer /webhooks/payments/{provider})
Route::post('/webhooks/platform-payments', PlatformPaymentWebhookController::class)
    ->middleware('throttle:120,1');

Route::post('/auth/login', [SessionController::class, 'login'])->middleware('throttle:5,1');

Route::post('/auth/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1');

Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed'])
    ->name('api.v1.auth.email.verify');

Route::middleware(['auth:sanctum', 'active.user', 'superadmin'])->group(function () {
    Route::post('/auth/admin/register', [RegisterAdminController::class, 'store'])->name('auth.admin.register');
});

Route::middleware(['auth:sanctum', 'active.user', 'company.context'])->group(function () {
    Route::get('/auth/me', [SessionController::class, 'me']);
    Route::post('/auth/logout', [SessionController::class, 'logout']);
    Route::post('/auth/logout-all', [SessionController::class, 'logoutAll']);
    Route::post('/auth/company/switch', [SessionController::class, 'switchCompany']);
    Route::put('/auth/password', [PasswordController::class, 'update']);
    Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1');
});
