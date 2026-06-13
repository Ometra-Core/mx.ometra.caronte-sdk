<?php

use Illuminate\Support\Facades\Route;
use Ometra\Caronte\Http\Controllers\ApiAuthController;

Route::prefix('api/caronte/auth')->name('caronte.api.auth.')->group(function (): void {
    Route::post('login', [ApiAuthController::class, 'login'])->name('login');
    Route::post('exchange', [ApiAuthController::class, 'exchange'])->name('exchange');

    Route::middleware('caronte.session')->group(function (): void {
        Route::get('me', [ApiAuthController::class, 'me'])->name('me');
        Route::post('logout', [ApiAuthController::class, 'logout'])->name('logout');
    });
});
