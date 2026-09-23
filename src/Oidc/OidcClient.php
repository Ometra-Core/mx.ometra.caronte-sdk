<?php

namespace Ometra\Caronte\Oidc;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OidcClient
{
    public function authorizationUrl(string $state, string $nonce, string $codeVerifier): string
    {
        return rtrim((string) config('caronte.oidc.issuer'), '/') . '/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('caronte.oidc.client_id'),
            'redirect_uri' => (string) config('caronte.oidc.redirect_uri'),
            'scope' => (string) config('caronte.oidc.scopes', 'openid profile email'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Pkce::challenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, string $codeVerifier): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => (string) config('caronte.oidc.client_id'),
            'client_secret' => (string) config('caronte.oidc.client_secret'),
            'redirect_uri' => (string) config('caronte.oidc.redirect_uri'),
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);
    }

    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => (string) config('caronte.oidc.client_id'),
            'client_secret' => (string) config('caronte.oidc.client_secret'),
            'refresh_token' => $refreshToken,
        ]);
    }

    private function tokenRequest(array $payload): array
    {
        $response = Http::acceptJson()
            ->asForm()
            ->withOptions(['verify' => (bool) config('caronte.tls_verify', true)])
            ->timeout((int) config('caronte.http.timeout', 10))
            ->post(rtrim((string) config('caronte.oidc.issuer'), '/') . '/oauth/token', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                (string) data_get($response->json(), 'error_description', 'OIDC token request failed.'),
                $response->status()
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Invalid OIDC token response.');
        }

        return $data;
    }
}
