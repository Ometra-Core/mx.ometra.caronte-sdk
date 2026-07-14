<?php

namespace Ometra\Caronte\Support;

use Illuminate\Http\Request;

final class CaronteCallbackUrl
{
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

        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! isset($parts['scheme'], $parts['host'])
        ) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : static::defaultPort($scheme);

        return hash_equals(strtolower($request->getScheme()), $scheme)
            && hash_equals(strtolower($request->getHost()), strtolower((string) $parts['host']))
            && $request->getPort() === $port;
    }

    private static function defaultPort(string $scheme): int
    {
        return $scheme === 'https' ? 443 : 80;
    }
}
