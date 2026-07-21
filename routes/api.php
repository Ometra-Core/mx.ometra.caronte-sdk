<?php

use Illuminate\Support\Facades\Route;
use Equidna\Toolkit\Http\Middleware\ForceJsonResponse;
use Ometra\Caronte\Http\Controllers\ApiAuthController;

Route::prefix('api/caronte/auth')->middleware(ForceJsonResponse::class)->name('caronte.api.auth.')->group(function (): void {
    if ((bool) config('caronte.routes.auth_enabled', true)) {
        Route::post('login', [ApiAuthController::class, 'login'])->name('login');
    }

    Route::middleware('caronte.session')->group(function (): void {
        Route::get('me', [ApiAuthController::class, 'me'])->name('me');
        Route::post('logout', [ApiAuthController::class, 'logout'])->name('logout');
    });
});
