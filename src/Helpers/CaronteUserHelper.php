<?php

/**
 * @author Gabriel Ruelas
 * @license MIT
 * @version 1.4.0
 *
 */

namespace Ometra\Caronte\Helpers;

use Ometra\Caronte\Models\CaronteUser;
use Ometra\Caronte\Models\CaronteUserMetadata;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Ometra\Caronte\Facades\Caronte as CaronteFacade;

class CaronteUserHelper
{
    private function __construct()
    {
        //
    }

    /**
     * Get the user's name by URI.
     *
     * @param string $uri_user User URI.
     * @return string User name, or 'User not found' if not found.
     */
    public static function getUserName(string $uri_user): string
    {
        try {
            $user = static::userQuery($uri_user)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return 'User not found';
        }

        return $user->name;
    }

    /**
     * Get the user's email by URI.
     *
     * @param string $uri_user User URI.
     * @return string User email, or 'User not found' if not found.
     */
    public static function getUserEmail(string $uri_user): string
    {
        try {
            $user = static::userQuery($uri_user)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return 'User not found';
        }

        return $user->email;
    }

    /**
     * Get a user's metadata value by URI and key.
     *
     * @param string $uri_user User URI.
     * @param string $key Metadata key.
     * @return string|null Metadata value, or null if not found.
     */
    public static function getUserMetadata(string $uri_user, string $key): string|null
    {
        $query = DB::table((new CaronteUserMetadata())->getTable())
            ->where('uri_user', $uri_user)
            ->where('key', $key);

        $tenantId = static::tenantId();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->value('value');
    }

    private static function userQuery(string $uriUser)
    {
        $query = CaronteUser::where('uri_user', $uriUser);
        $tenantId = static::tenantId();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    private static function tenantId(): ?string
    {
        try {
            $tenantId = trim((string) CaronteFacade::getTenantId());
        } catch (\Throwable) {
            return null;
        }

        return $tenantId !== '' ? $tenantId : null;
    }
}
