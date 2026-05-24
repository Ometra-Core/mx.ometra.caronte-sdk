<?php

namespace Ometra\Caronte\Support;

use InvalidArgumentException;

class ConfiguredRoles
{
    /**
     * @return array<int, array{name: string, description: string, uri_applicationRole: string}>
     */
    public static function all(): array
    {
        $roles = config('caronte.roles', []);
        $normalized = [];

        foreach ((array) $roles as $key => $role) {
            $entry = static::normalizeEntry($key, $role);
            $normalized[$entry['name']] = $entry;
        }

        $normalized['root'] ??= [
            'name' => 'root',
            'description' => 'Default super administrator role',
        ];

        return array_values(array_map(
            function (array $role): array {
                $role['uri_applicationRole'] = sha1(CaronteApplicationToken::appId() . $role['name']);

                return $role;
            },
            $normalized
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_values(array_map(
            fn(array $role): string => $role['name'],
            static::all()
        ));
    }

    /**
     * @return array<string, array{name: string, description: string, uri_applicationRole: string}>
     */
    public static function keyedByName(): array
    {
        $roles = [];

        foreach (static::all() as $role) {
            $roles[$role['name']] = $role;
        }

        return $roles;
    }

    /**
     * @return array<int, string>
     */
    public static function accessRoles(): array
    {
        $roles = array_values(array_filter(
            array_map('trim', (array) config('caronte.management.access_roles', ['root']))
        ));

        if ($roles === []) {
            $roles = ['root'];
        }

        return $roles;
    }

    public static function validate(): void
    {
        $knownRoles = static::names();

        foreach (static::accessRoles() as $role) {
            if ($role === 'root') {
                continue;
            }

            if (!in_array($role, $knownRoles, true)) {
                throw new InvalidArgumentException(
                    sprintf('Unknown management access role [%s]. Add it to caronte.roles first.', $role)
                );
            }
        }
    }

    /**
     * @param  int|string  $key
     * @param  mixed  $role
     * @return array{name: string, description: string}
     */
    private static function normalizeEntry(int|string $key, mixed $role): array
    {
        if (is_string($role)) {
            if (is_string($key) && !is_numeric($key)) {
                $name = static::normalizeName($key);
                $description = trim($role) !== '' ? trim($role) : static::defaultDescription($name);

                return compact('name', 'description');
            }

            $name = static::normalizeName($role);

            return [
                'name' => $name,
                'description' => static::defaultDescription($name),
            ];
        }

        if (!is_array($role)) {
            throw new InvalidArgumentException('Each configured role must be a string or array.');
        }

        $name = static::normalizeName((string) ($role['name'] ?? $key));
        $description = trim((string) ($role['description'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Each configured role must include a name.');
        }

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : static::defaultDescription($name),
        ];
    }

    private static function normalizeName(string $name): string
    {
        $normalized = strtolower(trim($name));

        if ($normalized === '') {
            throw new InvalidArgumentException('Configured role names cannot be empty.');
        }

        if (!preg_match('/^[a-z0-9_.-]+$/', $normalized)) {
            throw new InvalidArgumentException(
                sprintf('Configured role [%s] contains invalid characters.', $name)
            );
        }

        return $normalized;
    }

    private static function defaultDescription(string $name): string
    {
        return ucfirst(str_replace(['_', '-', '.'], ' ', $name));
    }
}
