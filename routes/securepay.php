<?php

use Illuminate\Support\Facades\Route;
use HabibTalib\SecurePay\Http\Controllers\SecurePayCallbackController;

Route::prefix('securepay')->group(function () {
    Route::post('/callback', [SecurePayCallbackController::class, 'callback'])
        ->name('securepay.callback')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    Route::get('/redirect', [SecurePayCallbackController::class, 'redirect'])
        ->name('securepay.redirect');
});
