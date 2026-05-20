<?php

namespace Ometra\Caronte\Support;

use InvalidArgumentException;

class ConfiguredScopes
{
    /**
     * @return array<int, array{scope: string, description: string}>
     */
    public static function all(): array
    {
        $scopes = config('caronte.protected_api.scopes', null);

        if ($scopes === null || $scopes === []) {
            $scopes = config('caronte.permissions', []);
        }

        $normalized = [];

        foreach ((array) $scopes as $key => $scope) {
            $entry = static::normalizeEntry($key, $scope);
            $normalized[$entry['scope']] = $entry;
        }

        return array_values($normalized);
    }

    /**
     * @param  int|string  $key
     * @return array{scope: string, description: string}
     */
    private static function normalizeEntry(int|string $key, mixed $scope): array
    {
        if (is_string($scope)) {
            if (is_string($key) && ! is_numeric($key)) {
                return [
                    'scope' => static::normalizeName($key),
                    'description' => trim($scope),
                ];
            }

            $name = static::normalizeName($scope);

            return [
                'scope' => $name,
                'description' => ucfirst(str_replace(['_', '-', '.'], ' ', $name)),
            ];
        }

        if (! is_array($scope)) {
            throw new InvalidArgumentException('Each configured protected API scope must be a string or array.');
        }

        return [
            'scope' => static::normalizeName((string) ($scope['scope'] ?? $scope['permission'] ?? $scope['name'] ?? $key)),
            'description' => trim((string) ($scope['description'] ?? '')),
        ];
    }

    private static function normalizeName(string $name): string
    {
        $normalized = strtolower(trim($name));

        if ($normalized === '') {
            throw new InvalidArgumentException('Configured protected API scope names cannot be empty.');
        }

        if (! preg_match('/^[a-z0-9_.:-]+$/', $normalized)) {
            throw new InvalidArgumentException(sprintf('Configured protected API scope [%s] contains invalid characters.', $name));
        }

        return $normalized;
    }
}
