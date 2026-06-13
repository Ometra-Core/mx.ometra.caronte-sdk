<?php

namespace Ometra\Caronte;

use Equidna\BeeHive\Tenancy\TenantContext;
use Equidna\Toolkit\Exceptions\UnauthorizedException;
use Equidna\Toolkit\Helpers\RouteHelper;
use Exception;
use Lcobucci\JWT\Token\Plain;
use Ometra\Caronte\Exceptions\TenantMissingException;
use Ometra\Caronte\Models\CaronteUser;
use Ometra\Caronte\Support\CaronteApplicationToken;
use RuntimeException;
use stdClass;
use Throwable;

final class Caronte
{
    private bool $newToken = false;

    public function checkToken(): bool
    {
        try {
            $this->getToken();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function getToken(): Plain
    {
        $cachedToken = $this->cachedRequestToken();

        if ($cachedToken instanceof Plain) {
            return $cachedToken;
        }

        $token = $this->rawToken();

        if (!is_string($token) || $token === '') {
            throw new UnauthorizedException('Token not found');
        }

        return CaronteUserToken::validateToken($token);
    }

    public function getUser(): stdClass
    {
        try {
            $token = $this->getToken();
            $user = CaronteUserToken::userPayload($token);

            if (!$user instanceof stdClass) {
                throw new RuntimeException('Invalid user payload.');
            }

            return $user;
        } catch (Exception $exception) {
            throw new UnauthorizedException(
                message: 'No user provided',
                errors: [$exception->getMessage()],
                previous: $exception
            );
        }
    }

    public function getTenantId(): string
    {
        $user = $this->getUser();

        if (!isset($user->tenant_id) || $user->tenant_id === null || $user->tenant_id === '') {
            throw new TenantMissingException('No tenant provided');
        }

        return (string) $user->tenant_id;
    }

    public static function getRouteUser(): string
    {
        return (string) (request()->route('uri_user') ?: '');
    }

    public function saveToken(string $token): void
    {
        if (! $this->hasRequestSession()) {
            return;
        }

        request()->session()->put((string) config('caronte.session_key', 'caronte.user_token'), $token);
    }

    public function clearToken(): void
    {
        if (! $this->hasRequestSession()) {
            return;
        }

        request()->session()->forget((string) config('caronte.session_key', 'caronte.user_token'));
    }

    public function setTokenWasExchanged(): void
    {
        $this->newToken = true;
    }

    public function tokenWasExchanged(): bool
    {
        return $this->newToken;
    }

    public function resetTokenWasExchanged(): void
    {
        $this->newToken = false;
    }

    public function echo(string $message): string
    {
        return $message;
    }

    public static function updateUserData(stdClass|string $user): void
    {
        if (is_string($user)) {
            $user = json_decode($user);
        }

        if (!$user instanceof stdClass) {
            return;
        }

        try {
            $tenantId = isset($user->tenant_id) && $user->tenant_id !== ''
                ? (string) $user->tenant_id
                : null;

            if ($tenantId === null) {
                return;
            }

            $localUser = static::withTenantContext(
                $tenantId,
                fn(): CaronteUser => CaronteUser::withoutGlobalScopes()->updateOrCreate(
                    [
                        'uri_user' => $user->uri_user,
                        'tenant_id' => $tenantId,
                    ],
                    [
                        'name' => $user->name,
                        'email' => $user->email,
                    ]
                )
            );

            $metadata = is_iterable($user->metadata ?? null) ? $user->metadata : [];

            foreach ($metadata as $item) {
                if (!isset($item->key)) {
                    continue;
                }

                $localUser->metadata()->updateOrCreate(
                    [
                        'uri_user' => $user->uri_user,
                        'tenant_id' => $tenantId,
                        'scope' => $item->scope ?? CaronteApplicationToken::appId(),
                        'key' => $item->key,
                    ],
                    [
                        'value' => $item->value ?? null,
                    ]
                );
            }
        } catch (Exception) {
            // Local sync is optional and should never break authentication flows.
        }
    }

    private static function withTenantContext(?string $tenantId, callable $callback): mixed
    {
        if ($tenantId === null || !function_exists('app')) {
            return $callback();
        }

        $app = app();
        $hadContext = $app->bound(TenantContext::class);
        $previousContext = $hadContext ? $app->make(TenantContext::class) : null;
        $previousTenantId = $previousContext instanceof TenantContext
            ? $previousContext->get()
            : null;

        $tenantContext = new TenantContext();
        $tenantContext->set($tenantId);
        $app->instance(TenantContext::class, $tenantContext);

        try {
            return $callback();
        } finally {
            if ($previousContext instanceof TenantContext) {
                $previousContext->set($previousTenantId);
                $app->instance(TenantContext::class, $previousContext);
            } elseif (method_exists($app, 'forgetInstance')) {
                $app->forgetInstance(TenantContext::class);
            }
        }
    }

    private function rawToken(): ?string
    {
        if (RouteHelper::isApi() || request()->is('api/*')) {
            return request()->bearerToken();
        }

        if (! $this->hasRequestSession()) {
            return null;
        }

        return request()->session()->get((string) config('caronte.session_key', 'caronte.user_token'));
    }

    private function cachedRequestToken(): ?Plain
    {
        if (! app()->bound('request')) {
            return null;
        }

        $token = request()->attributes->get('caronte.user_token');

        return $token instanceof Plain ? $token : null;
    }

    private function hasRequestSession(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        try {
            return request()->hasSession();
        } catch (Throwable) {
            return false;
        }
    }
}
