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

        if (!isset($user->id_tenant) || $user->id_tenant === null || $user->id_tenant === '') {
            throw new TenantMissingException('No tenant provided');
        }

        return (string) $user->id_tenant;
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

        $session = request()->session();
        $session->put((string) config('caronte.session_key', 'caronte.user_token'), $token);

        $tenantId = request()->attributes->get('caronte.current_tenant_id');
        $portfolio = $this->tenantTokenPortfolio();

        if (is_string($tenantId) && $tenantId !== '' && isset($portfolio[$tenantId])) {
            $portfolio[$tenantId]['token'] = $token;
            $session->put($this->tenantTokensSessionKey(), $portfolio);
        }
    }

    /** @param array<int, array<string, mixed>> $tokens */
    public function saveTenantTokens(array $tokens, ?string $preferredTenantId = null): void
    {
        if (! $this->hasRequestSession()) {
            return;
        }

        $portfolio = [];
        foreach ($tokens as $entry) {
            $tenantId = trim((string) ($entry['id_tenant'] ?? ''));
            $token = (string) ($entry['token'] ?? '');
            if ($tenantId === '' || $token === '') {
                continue;
            }
            $portfolio[$tenantId] = [
                'id_tenant' => $tenantId,
                'name' => (string) ($entry['name'] ?? ''),
                'token' => $token,
            ];
        }

        if ($portfolio === []) {
            return;
        }

        $selected = is_string($preferredTenantId) && isset($portfolio[$preferredTenantId])
            ? $preferredTenantId
            : (string) array_key_first($portfolio);
        request()->session()->put($this->tenantTokensSessionKey(), $portfolio);
        request()->session()->put($this->lastTenantSessionKey(), $selected);
        request()->session()->put(
            (string) config('caronte.session_key', 'caronte.user_token'),
            $portfolio[$selected]['token']
        );
    }

    /** @return array<int, array{id_tenant: string, name: string}> */
    public function getAvailableTenants(): array
    {
        $tenants = array_values(array_map(
            static fn(array $entry): array => [
                'id_tenant' => $entry['id_tenant'],
                'name' => $entry['name'],
            ],
            $this->tenantTokenPortfolio()
        ));

        if ($tenants !== [] || ! app()->bound('request')) {
            return $tenants;
        }

        $user = request()->attributes->get('caronte.user');
        $tenantId = is_object($user) ? trim((string) ($user->id_tenant ?? '')) : '';
        return $tenantId === '' ? [] : [[
            'id_tenant' => $tenantId,
            'name' => (string) ($user->tenant_name ?? ''),
        ]];
    }

    public function getCurrentTenantId(): ?string
    {
        if (app()->bound('request')) {
            $current = request()->attributes->get('caronte.current_tenant_id');
            if (is_string($current) && $current !== '') {
                return $current;
            }
        }

        if (! $this->hasRequestSession()) {
            return null;
        }

        $last = request()->session()->get($this->lastTenantSessionKey());
        return is_string($last) && isset($this->tenantTokenPortfolio()[$last]) ? $last : null;
    }

    /** @return array<string, array{id_tenant: string, name: string, token: string}> */
    public function tenantTokenPortfolio(): array
    {
        if (! $this->hasRequestSession()) {
            return [];
        }

        $stored = request()->session()->get($this->tenantTokensSessionKey(), []);
        return is_array($stored) ? $stored : [];
    }

    public function clearToken(): void
    {
        if (! $this->hasRequestSession()) {
            return;
        }

        request()->session()->forget((string) config('caronte.session_key', 'caronte.user_token'));
        request()->session()->forget($this->tenantTokensSessionKey());
        request()->session()->forget($this->lastTenantSessionKey());
    }

    public function clearCurrentToken(): void
    {
        if (! $this->hasRequestSession()) {
            return;
        }

        $tenantId = request()->attributes->get('caronte.current_tenant_id');
        $portfolio = $this->tenantTokenPortfolio();
        if (! is_string($tenantId) || ! isset($portfolio[$tenantId])) {
            $this->clearToken();
            return;
        }

        unset($portfolio[$tenantId]);
        if ($portfolio === []) {
            $this->clearToken();
            return;
        }

        $fallbackTenantId = (string) array_key_first($portfolio);
        request()->session()->put($this->tenantTokensSessionKey(), $portfolio);
        request()->session()->put($this->lastTenantSessionKey(), $fallbackTenantId);
        request()->session()->put(
            (string) config('caronte.session_key', 'caronte.user_token'),
            $portfolio[$fallbackTenantId]['token']
        );
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
            $tenantId = isset($user->id_tenant) && $user->id_tenant !== ''
                ? (string) $user->id_tenant
                : null;

            if ($tenantId === null) {
                return;
            }

            $localUser = static::withTenantContext(
                $tenantId,
                fn(): CaronteUser => CaronteUser::withoutGlobalScopes()->updateOrCreate(
                    [
                        'uri_user' => $user->uri_user,
                        'id_tenant' => $tenantId,
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
                        'id_tenant' => $tenantId,
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
        if (RouteHelper::isApi()) {
            return request()->bearerToken();
        }

        if (! $this->hasRequestSession()) {
            return null;
        }

        $tenantId = request()->attributes->get('caronte.current_tenant_id');
        $portfolio = $this->tenantTokenPortfolio();
        if (is_string($tenantId) && isset($portfolio[$tenantId])) {
            return $portfolio[$tenantId]['token'];
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

    private function tenantTokensSessionKey(): string
    {
        return (string) config('caronte.tenant_tokens_session_key', 'caronte.tenant_tokens');
    }

    private function lastTenantSessionKey(): string
    {
        return (string) config('caronte.last_tenant_session_key', 'caronte.last_tenant_id');
    }
}
