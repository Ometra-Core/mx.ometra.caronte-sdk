<?php

/**
 * @author Gabriel Ruelas
 * @license MIT
 * @version 1.4.0
 */

namespace Ometra\Caronte\Facades;

use Illuminate\Support\Facades\Facade;
use Ometra\Caronte\Caronte as CaronteClass;

/**
 * Facade for the Caronte client, providing static access to authentication and user methods.
 *
 * @method static \Lcobucci\JWT\Token\Plain getToken()
 * @method static string getTenantId()
 * @method static \stdClass getUser()
 * @method static string getRouteUser()
 * @method static void saveToken(string $token_str)
 * @method static void saveTenantTokens(array $tokens, ?string $preferredTenantId = null)
 * @method static array getAvailableTenants()
 * @method static ?string getCurrentTenantId()
 * @method static array tenantTokenPortfolio()
 * @method static void clearToken()
 * @method static void clearCurrentToken()
 * @method static void setTokenWasExchanged()
 * @method static bool tokenWasExchanged()
 * @method static void resetTokenWasExchanged()
 * @method static string echo(string $message)
 * @method static void updateUserData(\stdClass|string $user)
 *
 * @see Ometra\Caronte\Caronte
 */
class Caronte extends Facade
{
    /**
     * Get the accessor for the facade.
     *
     * @return string The class name of the accessor.
     */
    protected static function getFacadeAccessor(): string
    {
        return CaronteClass::class;
    }
}
