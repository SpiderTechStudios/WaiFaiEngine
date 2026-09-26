<?php

use Illuminate\Support\Facades\Route;

/*
| Gateway protocol endpoints live outside /v1 so Ruijie/WiFiDog can call
| /api/wifidog/* without API versioning (matches SensibleHotspot convention).
*/
require __DIR__.'/Api/wifidog.php';

Route::prefix('v1')->group(function () {
    require __DIR__.'/Api/v1/portal.php';
    require __DIR__.'/Api/v1/captive.php';
    require __DIR__.'/Api/v1/auth.php';
    require __DIR__.'/Api/v1/admin.php';
    require __DIR__.'/Api/v1/billing.php';
    require __DIR__.'/Api/v1/business.php';
    require __DIR__.'/Api/v1/installation_requests.php';
    require __DIR__.'/Api/v1/routers.php';
    require __DIR__.'/Api/v1/branches.php';
    require __DIR__.'/Api/v1/customers.php';
    require __DIR__.'/Api/v1/dashboard.php';
    require __DIR__.'/Api/v1/devices.php';
    require __DIR__.'/Api/v1/expenses.php';
    require __DIR__.'/Api/v1/offers.php';
    require __DIR__.'/Api/v1/packages.php';
    require __DIR__.'/Api/v1/payments.php';
    require __DIR__.'/Api/v1/session.php';
    require __DIR__.'/Api/v1/settings.php';
    require __DIR__.'/Api/v1/staff.php';
    require __DIR__.'/Api/v1/vouchers.php';
    require __DIR__.'/Api/v1/withdraw.php';
});
