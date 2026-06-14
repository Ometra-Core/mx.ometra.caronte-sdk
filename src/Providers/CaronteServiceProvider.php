<?php

namespace Ometra\Caronte\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Ometra\Caronte\Api\CaronteApiClient;
use Ometra\Caronte\Caronte;
use Ometra\Caronte\Console\Commands\ManagementCaronte;
use Ometra\Caronte\Console\Commands\Groups\ListGroupRoles;
use Ometra\Caronte\Console\Commands\Groups\ListGroupUsers;
use Ometra\Caronte\Console\Commands\Groups\SyncGroupUserRoles;
use Ometra\Caronte\Console\Commands\Permissions\SyncPermissions;
use Ometra\Caronte\Console\Commands\ProtectedApi\SyncScopes;
use Ometra\Caronte\Console\Commands\Roles\SyncRoles;
use Ometra\Caronte\Console\Commands\Tenants\ListTenants;
use Ometra\Caronte\Console\Commands\Tenants\ShowTenant;
use Ometra\Caronte\Console\Commands\Users\CreateUser;
use Ometra\Caronte\Console\Commands\Users\DeleteUser;
use Ometra\Caronte\Console\Commands\Users\ListUsers;
use Ometra\Caronte\Console\Commands\Users\SyncUserRoles;
use Ometra\Caronte\Console\Commands\Users\UpdateUser;
use Ometra\Caronte\Contracts\SendsPasswordRecovery;
use Ometra\Caronte\Contracts\SendsTwoFactorChallenge;
use Ometra\Caronte\Facades\Caronte as CaronteFacade;
use Ometra\Caronte\Helpers\PermissionHelper;
use Ometra\Caronte\Http\Middleware\ResolveApplicationContext;
use Ometra\Caronte\Http\Middleware\ValidateApplicationAccessPermissions;
use Ometra\Caronte\Http\Middleware\ValidateApplicationAccessToken;
use Ometra\Caronte\Http\Middleware\ValidateProtectedApiAccessToken;
use Ometra\Caronte\Http\Middleware\ValidateProtectedApiScopes;
use Ometra\Caronte\Http\Middleware\ValidateUserRoles;
use Ometra\Caronte\Http\Middleware\ValidateUserToken;
use Ometra\Caronte\Notifications\PasswordRecoverySender;
use Ometra\Caronte\Notifications\TwoFactorChallengeSender;
use Ometra\Caronte\Support\ConfiguredRoles;
use Ometra\Caronte\Support\CaronteTenancy;
use InvalidArgumentException;

class CaronteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/caronte.php', 'caronte');

        $this->app->singleton(Caronte::class, fn() => new Caronte());
        $this->app->singleton(CaronteApiClient::class, fn() => new CaronteApiClient());

        $this->app->bind(SendsTwoFactorChallenge::class, function ($app): SendsTwoFactorChallenge {
            $sender = $app->make((string) config(
                'caronte.notifications.two_factor_sender',
                TwoFactorChallengeSender::class
            ));

            if (!$sender instanceof SendsTwoFactorChallenge) {
                throw new InvalidArgumentException(sprintf(
                    'Caronte: %s must implement %s.',
                    $sender::class,
                    SendsTwoFactorChallenge::class
                ));
            }

            return $sender;
        });

        $this->app->bind(SendsPasswordRecovery::class, function ($app): SendsPasswordRecovery {
            $sender = $app->make((string) config(
                'caronte.notifications.password_recovery_sender',
                PasswordRecoverySender::class
            ));

            if (!$sender instanceof SendsPasswordRecovery) {
                throw new InvalidArgumentException(sprintf(
                    'Caronte: %s must implement %s.',
                    $sender::class,
                    SendsPasswordRecovery::class
                ));
            }

            return $sender;
        });
    }

    public function boot(Router $router): void
    {
        if ($this->shouldValidateCaronteConfig()) {
            $this->validateCaronteConfig();
        }

        $loader = AliasLoader::getInstance();
        $loader->alias('Caronte', CaronteFacade::class);
        $loader->alias('PermissionHelper', PermissionHelper::class);

        $router->aliasMiddleware('caronte.session', ValidateUserToken::class);
        $router->aliasMiddleware('caronte.roles', ValidateUserRoles::class);
        $router->aliasMiddleware('caronte.application', ResolveApplicationContext::class);
        $router->aliasMiddleware('caronte.protected-api-token', ValidateProtectedApiAccessToken::class);
        $router->aliasMiddleware('caronte.protected-api-scopes', ValidateProtectedApiScopes::class);
        $router->aliasMiddleware('caronte.app-token', ValidateApplicationAccessToken::class);
        $router->aliasMiddleware('caronte.app-permissions', ValidateApplicationAccessPermissions::class);

        Route::middleware(['web'])->group(function (): void {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        });

        Route::middleware(['api'])->group(function (): void {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        });

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'caronte');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->publishes(
            [
                __DIR__ . '/../../config/caronte.php' => config_path('caronte.php'),
            ],
            ['caronte:config', 'caronte']
        );

        $this->publishes(
            [
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/caronte'),
            ],
            ['caronte:views', 'caronte']
        );

        $this->publishes(
            [
                __DIR__ . '/../../resources/assets' => public_path('vendor/caronte'),
            ],
            ['caronte-assets', 'caronte']
        );

        $this->publishes(
            [
                __DIR__ . '/../../resources/js' => resource_path('js/vendor/caronte'),
            ],
            ['caronte:inertia', 'caronte']
        );

        $this->publishes(
            [
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ],
            ['caronte:migrations', 'caronte']
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ManagementCaronte::class,
                SyncScopes::class,
                SyncPermissions::class,
                SyncRoles::class,
                ListGroupRoles::class,
                ListGroupUsers::class,
                SyncGroupUserRoles::class,
                ListTenants::class,
                ShowTenant::class,
                ListUsers::class,
                CreateUser::class,
                UpdateUser::class,
                DeleteUser::class,
                SyncUserRoles::class,
            ]);
        }

        Inertia::share('caronte', function (): array {
            return [
                'branding' => config('caronte.ui.branding', []),
                'management' => [
                    'enabled' => (bool) config('caronte.management.enabled', true),
                    'access_roles' => ConfiguredRoles::accessRoles(),
                ],
                'user' => CaronteFacade::checkToken() ? CaronteFacade::getUser() : null,
            ];
        });
    }

    protected function validateCaronteConfig(): void
    {
        $required = [
            'caronte.url',
            'caronte.app_cn',
            'caronte.app_secret',
            'caronte.login_url',
        ];

        $missing = [];

        foreach ($required as $key) {
            $value = config($key);

            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if (config('caronte.enforce_issuer') && empty(config('caronte.issuer_id'))) {
            $missing[] = 'caronte.issuer_id';
        }

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Caronte: Missing required configuration: ' . implode(', ', $missing) . '.'
            );
        }

        $url = (string) config('caronte.url');
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme !== 'https' && ! (bool) config('caronte.allow_http_requests', false)) {
            throw new InvalidArgumentException(
                'Caronte: CARONTE_URL must use HTTPS unless CARONTE_ALLOW_HTTP_REQUESTS=true.'
            );
        }

        foreach (['caronte.application_token_ttl_seconds', 'caronte.application_group_token_ttl_seconds'] as $key) {
            if ((int) config($key, 300) < 1) {
                throw new InvalidArgumentException(
                    sprintf('Caronte: %s must be greater than zero.', $key)
                );
            }
        }

        CaronteTenancy::validateConfig();
        ConfiguredRoles::validate();
    }

    protected function shouldValidateCaronteConfig(): bool
    {
        if (!$this->app->runningInConsole()) {
            return true;
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? '';

        if (!is_string($command) || $command === '') {
            return false;
        }

        return str_starts_with($command, 'caronte:');
    }
}
