<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__ . '/Api/v1/auth.php';
    require __DIR__ . '/Api/v1/admin.php';
    require __DIR__ . '/Api/v1/routers.php';
    require __DIR__ . '/Api/v1/branches.php';
    require __DIR__ . '/Api/v1/customers.php';
    require __DIR__ . '/Api/v1/dashboard.php';
    require __DIR__ . '/Api/v1/packages.php';
    require __DIR__ . '/Api/v1/payments.php';
    require __DIR__ . '/Api/v1/session.php';
    require __DIR__ . '/Api/v1/settings.php';
    require __DIR__ . '/Api/v1/staff.php';
    require __DIR__ . '/Api/v1/vouchers.php';
    require __DIR__ . '/Api/v1/withdraw.php';

});
