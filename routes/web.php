<?php

use Illuminate\Support\Facades\Route;
use Ometra\Caronte\Http\Controllers\AuthController;
use Ometra\Caronte\Http\Controllers\ManagementController;
use Ometra\Caronte\Http\Controllers\OidcAuthController;
use Ometra\Caronte\Http\Controllers\RoleController;
use Ometra\Caronte\Http\Controllers\UserController;
use Ometra\Caronte\Support\ConfiguredRoles;

$authPrefix = trim((string) config('caronte.routes.prefix', ''), '/');
$managementPrefix = trim((string) config('caronte.management.route_prefix', 'caronte/management'), '/');
$managementRoles = implode(',', ConfiguredRoles::accessRoles());

Route::prefix($authPrefix)->name('caronte.')->group(function (): void {
    Route::match(['get', 'post'], 'logout', [AuthController::class, 'logout'])->name('logout');
});

if ((bool) config('caronte.routes.auth_enabled', true)) {
    $configuredLoginUrl = (string) config('caronte.routes.login_url', '/login');
    $loginPath = trim((string) (parse_url($configuredLoginUrl, PHP_URL_PATH) ?: $configuredLoginUrl), '/');

    Route::prefix($authPrefix)->name('caronte.')->group(function () use ($loginPath): void {
        Route::get($loginPath, [AuthController::class, 'loginForm'])->name('login.form');
        Route::post($loginPath, [AuthController::class, 'login'])->name('login');

        Route::get('oidc/login', [OidcAuthController::class, 'redirect'])->name('oidc.login');
        Route::get('oidc/callback', [OidcAuthController::class, 'callback'])->name('oidc.callback');
        Route::post('oidc/logout', [OidcAuthController::class, 'logout'])->name('oidc.logout');

        Route::post('two-factor', [AuthController::class, 'twoFactorTokenRequest'])->name('twoFactor.request');
        Route::get('two-factor/{token}', [AuthController::class, 'twoFactorTokenLogin'])->name('twoFactor.login');

        Route::prefix('password/recover')->name('password.recover.')->group(function (): void {
            Route::get('', [AuthController::class, 'passwordRecoverRequestForm'])->name('form');
            Route::post('', [AuthController::class, 'passwordRecoverRequest'])->name('request');
            Route::get('{token}', [AuthController::class, 'passwordRecoverTokenValidation'])->name('validate-token');
            Route::post('{token}', [AuthController::class, 'passwordRecover'])->name('submit');
        });
    });
}

if (config('caronte.management.enabled')) {
    Route::prefix($managementPrefix)
        ->name('caronte.management.')
        ->middleware(['caronte.session', "caronte.roles:{$managementRoles}"])
        ->group(function (): void {
            Route::get('', [ManagementController::class, 'dashboard'])->name('dashboard');
            Route::post('roles/sync', [RoleController::class, 'sync'])->name('roles.sync');

            Route::get('users/list', [UserController::class, 'list'])->name('users.list');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{uri_user}', [UserController::class, 'show'])->name('users.show');
            Route::put('users/{uri_user}', [UserController::class, 'update'])->name('users.update.direct');
            Route::delete('users/{uri_user}', [UserController::class, 'delete'])->name('users.delete.direct');
            Route::get('users/{uri_user}/roles', [UserController::class, 'listRoles'])->name('users.roles.list');
            Route::put('users/{uri_user}/roles', [UserController::class, 'syncRoles'])->name('users.roles.sync');
            Route::post('users/{uri_user}/metadata', [UserController::class, 'storeMetadata'])->name('users.metadata.store');
            Route::delete('users/{uri_user}/metadata', [UserController::class, 'deleteMetadata'])->name('users.metadata.delete');

            Route::put(
                'users/{uri_user}/applications/{app_id}/roles',
                [UserController::class, 'syncGroupRoles']
            )->name('users.applications.roles.sync');
        });
}
