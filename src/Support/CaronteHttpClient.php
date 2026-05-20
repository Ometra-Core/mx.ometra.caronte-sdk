<?php

namespace Ometra\Caronte\Support;

use Equidna\BeeHive\Tenancy\TenantContext;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Ometra\Caronte\Exceptions\CaronteApiException;
use Ometra\Caronte\Facades\Caronte;

/**
 * Base HTTP client for service-to-service communication.
 *
 * Provides common logic for request building, response parsing, and tenant context
 * resolution. Subclasses must implement getBaseUrl() and makeApplicationToken().
 */
abstract class CaronteHttpClient
{
    /**
     * Make an application-authenticated request.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public function applicationRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = [],
    ): array {
        $headers = $this->applicationHeaders();

        $tenantId = $this->tenantContextId();

        if ($tenantId !== null) {
            $headers['X-Tenant-Id'] = $tenantId;
        }

        return $this->request($method, $endpoint, $payload, $query, $headers);
    }

    /**
     * Make a user-authenticated request (using current user's JWT token).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public function userRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = [],
    ): array {
        $headers = $this->applicationHeaders() + [
            'X-User-Token' => Caronte::getToken()->toString(),
        ];

        $tenantId = $this->tenantContextId();

        if ($tenantId !== null) {
            $headers['X-Tenant-Id'] = $tenantId;
        }

        return $this->request($method, $endpoint, $payload, $query, $headers);
    }

    /**
     * Get the base URL for requests.
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Generate the X-Application-Token header value.
     */
    abstract protected function makeApplicationToken(): string;

    /**
     * @return array<string, string>
     */
    protected function applicationHeaders(): array
    {
        $headers = [
            'X-Application-Token' => $this->makeApplicationToken(),
        ];

        $groupToken = $this->makeGroupToken();

        if (is_string($groupToken) && $groupToken !== '') {
            $headers['X-Group-Token'] = $groupToken;
        }

        return $headers;
    }

    protected function makeGroupToken(): ?string
    {
        return CaronteApplicationToken::hasGroup()
            ? CaronteApplicationToken::makeGroup()
            : null;
    }

    private function tenantContextId(): ?string
    {
        if (! app()->bound(TenantContext::class)) {
            return null;
        }

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);
        $resolved = $tenantContext->get();

        return is_string($resolved) && trim($resolved) !== '' ? trim($resolved) : null;
    }

    /**
     * Execute the HTTP request and return parsed response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    protected function request(
        string $method,
        string $endpoint,
        array $payload,
        array $query,
        array $headers,
    ): array {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');
        $urlWithQuery = $query !== [] ? $url . '?' . http_build_query($query) : $url;
        $method = strtolower($method);

        $request = Http::acceptJson()
            ->withOptions([
                'verify' => (bool) config('caronte.tls_verify', true),
            ])
            ->timeout((int) config('caronte.http.timeout', 10))
            ->retry(
                times: (int) config('caronte.http.retries', 1),
                sleepMilliseconds: (int) config('caronte.http.retry_sleep', 150)
            )
            ->withHeaders($headers);

        $response = match ($method) {
            'get'    => $request->get($url, $query),
            'delete' => $request->delete($url, $payload !== [] ? $payload : $query),
            'post'   => $request->post($urlWithQuery, $payload),
            'put'    => $request->put($urlWithQuery, $payload),
            'patch'  => $request->patch($urlWithQuery, $payload),
            default  => throw new CaronteApiException("Unsupported HTTP method [{$method}].", 500),
        };

        return $this->parseResponse($response);
    }

    /**
     * Parse and normalize HTTP response.
     *
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    protected function parseResponse(Response $response): array
    {
        $payload = $response->json();

        if (!is_array($payload)) {
            $payload = [
                'status'  => $response->status(),
                'message' => $response->successful() ? 'Request completed successfully.' : 'Unexpected response from service.',
                'data'    => null,
                'errors'  => [],
            ];
        }

        $status  = (int) ($payload['status'] ?? $response->status());
        $message = (string) ($payload['message'] ?? $response->reason());
        $errors  = is_array($payload['errors'] ?? null) ? $payload['errors'] : [];

        if ($response->failed()) {
            throw new CaronteApiException(
                message: $message !== '' ? $message : 'Service request failed.',
                status: $status > 0 ? $status : $response->status(),
                errors: $errors,
                payload: $payload,
            );
        }

        return [
            'status'  => $status > 0 ? $status : $response->status(),
            'message' => $message !== '' ? $message : 'Request completed successfully.',
            'data'    => $payload['data'] ?? null,
            'errors'  => $errors,
        ];
    }
}
