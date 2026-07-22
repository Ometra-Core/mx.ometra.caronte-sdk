<?php

namespace Ometra\Caronte\Support;

use Illuminate\Http\Request;

final class CaronteCallbackUrl
{
    private const POLICY_STRICT_SAME_HOST = 'strict_same_host';
    private const POLICY_ALLOWLIST = 'allowlist';

    public static function resolve(Request $request, mixed $candidate): string
    {
        return static::normalize($request, $candidate)
            ?? (string) config('caronte.routes.success_url', '/');
    }

    public static function normalize(Request $request, mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $value = trim($candidate);

        if ($value === '') {
            return null;
        }

        if (static::isAllowed($request, $value)) {
            return $value;
        }

        $decoded = base64_decode($value, true);

        if (! is_string($decoded) || $decoded === '' || ! mb_check_encoding($decoded, 'UTF-8')) {
            return null;
        }

        $decoded = trim($decoded);

        return static::isAllowed($request, $decoded) ? $decoded : null;
    }

    private static function isAllowed(Request $request, string $url): bool
    {
        $decodedUrl = rawurldecode($url);

        if (
            $url === ''
            || preg_match('/[\x00-\x1F\x7F]/', $decodedUrl) === 1
            || str_contains($decodedUrl, '\\')
            || str_starts_with($decodedUrl, '//')
        ) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) {
                return false;
            }

            $parts = parse_url($url);

            return is_array($parts)
                && ! isset($parts['scheme'])
                && ! isset($parts['host'])
                && ! isset($parts['user'])
                && ! isset($parts['pass']);
        }

        $parts = static::parseAbsoluteUrl($url);

        if ($parts === null) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : static::defaultPort($scheme);

        if (! hash_equals(strtolower($request->getScheme()), $scheme) || $request->getPort() !== $port) {
            return false;
        }

        if (static::policy() === self::POLICY_ALLOWLIST) {
            return static::isAllowedHost($host);
        }

        return hash_equals(strtolower($request->getHost()), $host);
    }

    private static function policy(): string
    {
        $policy = strtolower((string) config('caronte.callback_url.policy', self::POLICY_STRICT_SAME_HOST));

        return in_array($policy, [self::POLICY_STRICT_SAME_HOST, self::POLICY_ALLOWLIST], true)
            ? $policy
            : self::POLICY_STRICT_SAME_HOST;
    }

    /** @return array<int, string> */
    private static function allowedHosts(): array
    {
        $hosts = config('caronte.callback_url.allowed_hosts', []);

        if (! is_array($hosts)) {
            return [];
        }

        $normalizedHosts = [];

        foreach ($hosts as $host) {
            if (! is_string($host)) {
                continue;
            }

            $normalized = strtolower(trim($host));

            if ($normalized === '') {
                continue;
            }

            $normalizedHosts[] = $normalized;
        }

        return array_values(array_unique($normalizedHosts));
    }

    private static function isAllowedHost(string $host): bool
    {
        foreach (static::allowedHosts() as $allowedHost) {
            if (hash_equals($allowedHost, $host)) {
                return true;
            }

            if (! str_starts_with($allowedHost, '*.')) {
                continue;
            }

            $suffix = substr($allowedHost, 1);

            if ($suffix !== '.' && str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{scheme: string, host: string, port?: int}|null */
    private static function parseAbsoluteUrl(string $url): ?array
    {
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! isset($parts['scheme'], $parts['host'])
            || ! is_string($parts['scheme'])
            || ! is_string($parts['host'])
        ) {
            return null;
        }

        return $parts;
    }

    private static function defaultPort(string $scheme): int
    {
        return $scheme === 'https' ? 443 : 80;
    }
}
