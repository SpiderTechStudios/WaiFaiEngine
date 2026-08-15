<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/Api/v1/auth.php';
    require __DIR__.'/Api/v1/admin.php';
});
