# Tests Documentation

## Overview

The package uses PHPUnit 11 through Orchestra Testbench 10.

```bash
composer test
php vendor/bin/phpunit --filter MiddlewareBehaviorTest
npm run typecheck
```

## Feature Coverage

| Area                                                                                  | Test file                           |
| ------------------------------------------------------------------------------------- | ----------------------------------- |
| Auth pages, login, logout, password recovery, 2FA                                     | `AuthContractTest`                  |
| Commands for roles, protected API scopes, and users                                  | `CommandBehaviorTest`               |
| Config validation for roles and management access                                     | `ConfigurationValidationTest`       |
| Management UI routes                                                                  | `ManagementUiTest`                  |
| Resolved tenancy, Inertia, and helper contracts                                       | `ResolvedOpenQuestionsTest`         |
| Disabled management routes                                                            | `ManagementUiWhenDisabledTest`      |
| Middleware: user session, roles, app auth token, group auth token, protected API access token | `MiddlewareBehaviorTest`            |
| Route registration                                                                    | `RouteRegistrationTest`             |
| Disabled route registration                                                           | `RouteRegistrationWhenDisabledTest` |

## Important Contracts Covered

- JWT app credentials and JWT group credentials are accepted by `caronte.application`.
- Group user JWTs validate against group id and group secret.
- Expired user JWTs trigger exchange.
- Phase-2 user JWT claims are preferred and tokens without the legacy `user` claim are accepted.
- SDK logout sends application and user tokens to the server logout endpoint.
- `ProvisioningApi` wraps the server tenant provisioning endpoint.
- `CaronteTenantResolver` reads tenant id from the authenticated user JWT.
- Management dashboard supports Inertia responses when enabled.
- `CaronteUserHelper` reads local cache name, email, and metadata values.
- Role middleware rejects missing roles.
- `caronte.protected-api-token` accepts valid Protected API Access Tokens.
- `caronte.protected-api-scopes` rejects missing scopes.
- `caronte:protected-api:scopes:sync` sends the configured protected API scope catalog to Caronte.
- Legacy `caronte.app-token`, `caronte.app-permissions`, `caronte:permissions:sync`, and `permissions` terminology remains covered only as deprecated compatibility until the next major version.

## Test Helpers

`tests/TestCase.php` provides:

- `makeToken()` for user JWTs.
- `makeProtectedApiAccessToken()` for Protected API Access Token fixtures using `protected_api_access` and `scopes`.
- `makeApplicationAccessToken()` only for legacy compatibility fixtures using `application_token` and `permissions`.

Both helpers sign tokens with the configured test secrets.
