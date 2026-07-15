<?php

namespace Ometra\Caronte\Api;

use Ometra\Caronte\Support\CaronteApplicationToken;
use Ometra\Caronte\Support\CaronteHttpClient;

/**
 * HTTP client for communicating with the Caronte authentication server.
 *
 * Provides three authentication modes:
 * - authRequest: Application + optional user token (for auth operations)
 * - applicationRequest: Application token only (inherited from CaronteHttpClient)
 * - userRequest: Current user's JWT token (inherited from CaronteHttpClient)
 */
final class CaronteApiClient extends CaronteHttpClient
{
    /**
     * Make an auth operation request (with optional user token override).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public function authRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = [],
        ?string $userToken = null,
    ): array {
        $headers = $this->applicationHeaders();

        if (is_string($userToken) && $userToken !== '') {
            $headers['X-User-Token'] = $userToken;
        }

        return $this->request(
            $method,
            $endpoint,
            $payload,
            $query,
            $headers
        );
    }

    protected function getBaseUrl(): string
    {
        return (string) config('caronte.url');
    }

    protected function makeApplicationToken(): string
    {
        return CaronteApplicationToken::make();
    }
}
