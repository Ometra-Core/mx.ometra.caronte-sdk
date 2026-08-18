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
use Ometra\Caronte\Console\Commands\Groups\ShowGroup;
use Ometra\Caronte\Console\Commands\Groups\SyncGroupUserRoles;
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
use Ometra\Caronte\Http\Middleware\BlockLoginRouteWhenDisabled;
use Ometra\Caronte\Http\Middleware\ResolveApplicationContext;
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
        $router->aliasMiddleware('caronte.block-login-route-when-disabled', BlockLoginRouteWhenDisabled::class);

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
                SyncRoles::class,
                ShowGroup::class,
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
                'tenants' => CaronteFacade::getAvailableTenants(),
                'current_tenant_id' => CaronteFacade::getCurrentTenantId(),
            ];
        });
    }

    protected function validateCaronteConfig(): void
    {
        $required = [
            'caronte.url',
            'caronte.app_cn',
            'caronte.app_secret',
            'caronte.issuer_id',
            'caronte.routes.login_url',
        ];

        $missing = [];

        foreach ($required as $key) {
            $value = config($key);

            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if (! in_array(config('caronte.auth_mode'), ['jwt', 'oidc', 'dual'], true)) {
            throw new InvalidArgumentException(
                'Caronte: caronte.auth_mode must be jwt, oidc, or dual.'
            );
        }

        $accessMode = config('caronte.access.mode', 'application_role');
        if (! in_array($accessMode, ['application_role', 'application_group'], true)) {
            throw new InvalidArgumentException(
                'Caronte: caronte.access.mode must be application_role or application_group.'
            );
        }

        if ($accessMode === 'application_group' && ! CaronteApplicationToken::hasGroup()) {
            throw new InvalidArgumentException(
                'Caronte: application_group access requires CARONTE_APPLICATION_GROUP_ID and CARONTE_APPLICATION_GROUP_SECRET.'
            );
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

        if (! (bool) config('caronte.routes.auth_enabled', true)) {
            $loginUrl = (string) config('caronte.routes.login_url');
            $loginScheme = parse_url($loginUrl, PHP_URL_SCHEME);
            $loginHost = parse_url($loginUrl, PHP_URL_HOST);

            if (! is_string($loginHost) || $loginHost === '' || ! in_array($loginScheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException(
                    'Caronte: CARONTE_LOGIN_URL must be an absolute HTTP(S) URL when authentication routes are disabled.'
                );
            }

            if ($loginScheme !== 'https' && ! (bool) config('caronte.allow_http_requests', false)) {
                throw new InvalidArgumentException(
                    'Caronte: delegated CARONTE_LOGIN_URL must use HTTPS unless CARONTE_ALLOW_HTTP_REQUESTS=true.'
                );
            }
        }

        if ((int) config('caronte.token.ttl_seconds', 300) < 1) {
            throw new InvalidArgumentException(
                'Caronte: caronte.token.ttl_seconds must be greater than zero.'
            );
        }

        foreach (['clock_skew_seconds', 'refresh_leeway_seconds'] as $key) {
            if ((int) config("caronte.token.{$key}", 60) < 0) {
                throw new InvalidArgumentException(
                    "Caronte: caronte.token.{$key} must be zero or greater."
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
