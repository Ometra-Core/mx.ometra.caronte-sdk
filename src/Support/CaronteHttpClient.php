<?php

namespace Ometra\Caronte\Support;

use Equidna\BeeHive\Tenancy\TenantContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Ometra\Caronte\Exceptions\CaronteApiException;
use Ometra\Caronte\Facades\Caronte;
use RuntimeException;

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
        ?string $userToken = null,
    ): array {
        $headers = $this->applicationHeaders();

        if (is_string($userToken) && trim($userToken) !== '') {
            $headers['X-User-Token'] = trim($userToken);
        }

        return $this->request(
            $method,
            $endpoint,
            $payload,
            $query,
            $this->withTenantHeader($headers)
        );
    }

    /**
     * Make an application-authenticated request without parsing the response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    public function applicationRawRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = [],
        ?string $userToken = null,
    ): Response {
        $headers = $this->applicationHeaders();

        if (is_string($userToken) && trim($userToken) !== '') {
            $headers['X-User-Token'] = trim($userToken);
        }

        return $this->rawRequest(
            $method,
            $endpoint,
            $payload,
            $query,
            $this->withTenantHeader($headers)
        );
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

        return $this->request(
            $method,
            $endpoint,
            $payload,
            $query,
            $this->withTenantHeader($headers)
        );
    }

    /**
     * Make a user-authenticated request without parsing the response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    public function userRawRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = [],
    ): Response {
        $headers = $this->applicationHeaders() + [
            'X-User-Token' => Caronte::getToken()->toString(),
        ];

        return $this->rawRequest(
            $method,
            $endpoint,
            $payload,
            $query,
            $this->withTenantHeader($headers)
        );
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
        $groupToken = $this->makeGroupToken();

        if (is_string($groupToken) && $groupToken !== '') {
            return ['X-Group-Token' => $groupToken];
        }

        return ['X-Application-Token' => $this->makeApplicationToken()];
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
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function withTenantHeader(array $headers): array
    {
        $tenantId = $this->tenantContextId();

        if ($tenantId !== null) {
            $headers['X-Tenant-Id'] = $tenantId;
        }

        return $headers;
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
        return $this->parseResponse(
            $this->rawRequest(
                $method,
                $endpoint,
                $payload,
                $query,
                $headers,
                acceptJson: true
            )
        );
    }

    /**
     * Execute an HTTP request without parsing the response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    protected function rawRequest(
        string $method,
        string $endpoint,
        array $payload,
        array $query,
        array $headers,
        bool $acceptJson = false,
    ): Response {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');
        $urlWithQuery = $query !== [] ? $url . '?' . http_build_query($query) : $url;
        $method = strtolower($method);

        $request = $this->httpRequest($headers, $acceptJson);

        if ($this->shouldUseMultipart($payload)) {
            $request = $request->asMultipart();
            $payload = $this->toMultipart($payload);
        }

        return match ($method) {
            'get'    => $request->get($url, $query),
            'delete' => $request->delete($url, $payload !== [] ? $payload : $query),
            'post'   => $request->post($urlWithQuery, $payload),
            'put'    => $request->put($urlWithQuery, $payload),
            'patch'  => $request->patch($urlWithQuery, $payload),
            default  => throw new CaronteApiException("Unsupported HTTP method [{$method}].", 500),
        };
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function httpRequest(array $headers, bool $acceptJson): PendingRequest
    {
        return Http::accept($acceptJson ? 'application/json' : '*/*')
            ->withOptions([
                'verify' => (bool) config('caronte.tls_verify', true),
            ])
            ->timeout((int) config('caronte.http.timeout', 10))
            ->retry(
                times: (int) config('caronte.http.retries', 1),
                sleepMilliseconds: (int) config('caronte.http.retry_sleep', 150)
            )
            ->withHeaders($headers);
    }

    /** @param array<string, mixed> $payload */
    private function shouldUseMultipart(array $payload): bool
    {
        foreach ($payload as $value) {
            if ($value instanceof UploadedFile || is_resource($value)) {
                return true;
            }

            if (is_array($value) && $this->shouldUseMultipart($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name: string, contents: mixed, filename?: string}>
     */
    private function toMultipart(array $payload, ?string $prefix = null): array
    {
        $parts = [];

        foreach ($payload as $key => $value) {
            $name = $prefix === null ? (string) $key : "{$prefix}[{$key}]";

            if ($value instanceof UploadedFile) {
                $parts[] = $this->filePart($name, $value);
                continue;
            }

            if (is_resource($value)) {
                $parts[] = ['name' => $name, 'contents' => $value];
                continue;
            }

            if (is_array($value)) {
                if ($this->isListOfFiles($value)) {
                    foreach ($value as $file) {
                        $parts[] = $this->filePart($name . '[]', $file);
                    }
                    continue;
                }

                $parts = array_merge($parts, $this->toMultipart($value, $name));
                continue;
            }

            $parts[] = ['name' => $name, 'contents' => $this->scalarPartContents($value)];
        }

        return $parts;
    }

    /** @param array<int|string, mixed> $value */
    private function isListOfFiles(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! $item instanceof UploadedFile) {
                return false;
            }
        }

        return true;
    }

    /** @return array{name: string, contents: resource, filename: string} */
    private function filePart(string $name, UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path)) {
            throw new RuntimeException("Cannot read uploaded file [{$file->getClientOriginalName()}].");
        }

        $stream = fopen($path, 'r');

        if (! is_resource($stream)) {
            throw new RuntimeException("Cannot open uploaded file [{$file->getClientOriginalName()}].");
        }

        return [
            'name' => $name,
            'contents' => $stream,
            'filename' => $file->getClientOriginalName(),
        ];
    }

    private function scalarPartContents(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? '' : (string) $value;
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
