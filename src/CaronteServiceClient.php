<?php

namespace Ometra\Caronte;

use Ometra\Caronte\Support\CaronteApplicationToken;
use Ometra\Caronte\Support\CaronteHttpClient;

/**
 * HTTP client for inter-service communication between host app instances.
 *
 * Both the caller and the target are host apps protected by Caronte middleware
 * (ValidateUserToken / ValidateUserRoles). This client re-uses the same token
 * strategy as CaronteApiClient but targets a configurable base URL instead of
 * the Caronte authentication server.
 *
 * When calling a service with different Caronte credentials, provide its
 * `appCn` and `appSecret` so the token is accepted by that service's
 * ResolveApplicationContext middleware. The secret is used only to sign the
 * outgoing JWT and is never placed directly in the request headers.
 *
 * Usage:
 *   // Same-credentials service:
 *   $client = new ServiceClient('https://service-b.example.com');
 *
 *   // Service with its own Caronte credentials:
 *   $client = new ServiceClient(
 *       baseUrl: 'https://service-b.example.com',
 *       appCn:     'service-b-cn',
 *       appSecret: 'service-b-secret',
 *   );
 */
class CaronteServiceClient extends CaronteHttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $appCn = null,
        private readonly ?string $appSecret = null,
    ) {}

    protected function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function makeApplicationToken(): string
    {
        if ($this->appCn !== null && $this->appSecret !== null) {
            return CaronteApplicationToken::makeFor($this->appCn, $this->appSecret);
        }

        return CaronteApplicationToken::make();
    }
}
